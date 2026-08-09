<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Services\CourierWebhookLogger;
use App\Services\SteadfastStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SteadfastWebhookController extends Controller
{

    public function __invoke(
        Request $request,
        CourierAccount $courierAccount,
        SteadfastStatusService $statusService,
        CourierWebhookLogger $webhookLogger
    ): JsonResponse {
        if (
            strtolower((string) $courierAccount->code) !== 'steadfast'
            || ! $courierAccount->status
            || ! $courierAccount->setting('webhook_enabled', false)
        ) {
            return response()->json([
                'status' => 'success',
                'message' => 'Webhook ignored because this courier account is inactive.',
            ], 200);
        }

        $expectedToken = trim((string) $courierAccount->setting('webhook_bearer_token'));
        $providedToken = $this->resolveBearerToken($request);

        if (
            $expectedToken === ''
            || $providedToken === ''
            || ! hash_equals($expectedToken, $providedToken)
        ) {
            Log::warning('SteadFast webhook authentication failed.', [
                'courier_account_id' => $courierAccount->id,
                'authorization_header_present' => $this->authorizationHeaderPresent($request),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Unauthorized.',
            ], 401)->header('WWW-Authenticate', 'Bearer');
        }

        $rawPayload = $request->all();
        $payload = $this->normalizePayload($rawPayload, $statusService);
        $notificationType = strtolower(trim((string) ($payload['notification_type'] ?? '')));

        if (
            $notificationType !== ''
            && ! in_array($notificationType, [
                'delivery_status',
                'delivery_status_update',
                'status_update',
                'tracking_update',
                'tracking_status_update',
            ], true)
        ) {
            Log::info('SteadFast webhook notification ignored.', [
                'courier_account_id' => $courierAccount->id,
                'notification_type' => $notificationType,
                'payload_keys' => array_keys($rawPayload),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook notification type ignored.',
            ], 200);
        }

        $validator = Validator::make($payload, [
            'notification_type' => ['nullable', 'string', 'max:100'],
            'consignment_id' => ['required', 'string', 'max:255'],
            'invoice' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:100'],
            'tracking_id' => ['nullable', 'string', 'max:255'],
            'tracking_message' => ['nullable', 'string', 'max:500'],
            'cod_amount' => ['nullable'],
            'updated_at' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            Log::warning('SteadFast webhook payload validation failed.', [
                'courier_account_id' => $courierAccount->id,
                'errors' => $validator->errors()->toArray(),
                'payload_keys' => array_keys($rawPayload),
            ]);

            /*
             * Acknowledge with 202 so one provider-side payload variation does
             * not create an endless retry loop. The API fallback sync can still
             * reconcile courier status for orders already sent to SteadFast.
             */
            return response()->json([
                'status' => 'accepted',
                'message' => 'Webhook payload accepted for fallback reconciliation.',
                'errors' => $validator->errors(),
            ], 202);
        }

        $payload = $validator->validated();
        $normalizedStatus = $statusService->normalize($payload['status']);
        $payload['status'] = $normalizedStatus;
        $eventKey = $this->eventKey($courierAccount, $payload);

        $log = $webhookLogger->start([
            'courier_account_id' => $courierAccount->id,
            'provider' => 'steadfast',
            'source' => 'webhook',
            'event_key' => $eventKey,
            'event_name' => $payload['notification_type'] ?: 'delivery_status',
            'external_event_id' => $payload['tracking_id'] ?: null,
            'consignment_id' => $payload['consignment_id'],
            'invoice' => $payload['invoice'],
            'courier_status' => $normalizedStatus,
            'mapped_status' => $statusService->category($normalizedStatus),
            'signature_valid' => true,
            'result' => 'received',
            'headers' => $webhookLogger->safeHeaders($request->headers->all()),
            'payload' => $rawPayload,
            'received_at' => now(),
        ]);

        if ($webhookLogger->completed($log)) {
            return response()->json([
                'status' => 'success',
                'message' => 'Webhook already handled.',
            ], 200);
        }

        try {
            $order = $this->findOrder(
                $courierAccount,
                $payload['consignment_id'],
                $payload['invoice']
            );

            if (! $order) {
                $webhookLogger->ignored($log, 'Matching order not found.');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Webhook accepted; matching order not found.',
                ], 200);
            }

            DB::transaction(function () use (
                $statusService,
                $order,
                $courierAccount,
                $payload,
                $rawPayload,
                $log,
                $webhookLogger
            ): void {
                $statusService->apply(
                    $order,
                    $courierAccount,
                    $payload['status'],
                    $rawPayload,
                    'webhook'
                );

                $webhookLogger->processed($log, [
                    'order_id' => $order->id,
                ]);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed successfully.',
            ], 200);
        } catch (Throwable $exception) {
            report($exception);

            $webhookLogger->failed($log, $exception);

            Log::error('SteadFast webhook processing failed; API fallback sync will reconcile the order.', [
                'courier_account_id' => $courierAccount->id,
                'event_key' => $eventKey,
                'consignment_id' => $payload['consignment_id'],
                'invoice' => $payload['invoice'],
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => 'accepted',
                'message' => 'Webhook accepted for fallback status reconciliation.',
            ], 202);
        }
    }

    private function findOrder(
        CourierAccount $courierAccount,
        string $consignmentId,
        string $invoice
    ): ?Order {
        $baseQuery = Order::query()
            ->where(function ($query) use ($courierAccount) {
                $query->where('courier_account_id', $courierAccount->id)
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('courier_account_id')
                            ->where('courier_service', 'steadfast');
                    });
            });

        $byConsignment = (clone $baseQuery)
            ->where('steadfast_consignment_id', $consignmentId)
            ->latest('id')
            ->first();

        if ($byConsignment) {
            return $byConsignment;
        }

        return (clone $baseQuery)
            ->where('invoice_id', $invoice)
            ->latest('id')
            ->first();
    }

    private function normalizePayload(
        array $payload,
        SteadfastStatusService $statusService
    ): array {
        $trackingMessage = $this->scalarString(
            data_get($payload, 'tracking_message')
                ?: data_get($payload, 'data.tracking_message'),
            true
        );

        $statusCandidate = $this->firstScalarString([
            data_get($payload, 'status'),
            data_get($payload, 'delivery_status'),
            data_get($payload, 'tracking_status'),
            data_get($payload, 'consignment.status'),
            data_get($payload, 'data.status'),
            $trackingMessage,
        ]);

        return [
            'notification_type' => $this->scalarString(
                data_get($payload, 'notification_type')
                    ?: data_get($payload, 'data.notification_type'),
                true
            ),
            'consignment_id' => $this->scalarString(
                data_get($payload, 'consignment_id')
                    ?: data_get($payload, 'data.consignment_id')
            ),
            'invoice' => $this->scalarString(
                data_get($payload, 'invoice')
                    ?: data_get($payload, 'data.invoice')
            ),
            'status' => $statusService->normalize($statusCandidate),
            'tracking_id' => $this->scalarString(
                data_get($payload, 'tracking_id')
                    ?: data_get($payload, 'data.tracking_id'),
                true
            ),
            'tracking_message' => $trackingMessage,
            'cod_amount' => data_get($payload, 'cod_amount')
                ?? data_get($payload, 'data.cod_amount'),
            'updated_at' => $this->scalarString(
                data_get($payload, 'updated_at')
                    ?: data_get($payload, 'data.updated_at'),
                true
            ),
        ];
    }

    private function firstScalarString(array $values): string
    {
        foreach ($values as $value) {
            $resolved = $this->scalarString($value);

            if ($resolved !== '') {
                return $resolved;
            }
        }

        return '';
    }

    private function scalarString(mixed $value, bool $nullable = false): ?string
    {
        if ($value === null || $value === '') {
            return $nullable ? null : '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function resolveBearerToken(Request $request): string
    {
        $token = trim((string) $request->bearerToken());

        if ($token !== '') {
            return $token;
        }

        foreach ([
            $request->header('Authorization'),
            $request->server('HTTP_AUTHORIZATION'),
            $request->server('REDIRECT_HTTP_AUTHORIZATION'),
        ] as $authorization) {
            if (
                is_string($authorization)
                && preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)
            ) {
                return trim($matches[1]);
            }
        }

        return trim((string) $request->header('X-SteadFast-Token', ''));
    }

    private function authorizationHeaderPresent(Request $request): bool
    {
        return $request->headers->has('Authorization')
            || filled($request->server('HTTP_AUTHORIZATION'))
            || filled($request->server('REDIRECT_HTTP_AUTHORIZATION'))
            || $request->headers->has('X-SteadFast-Token');
    }

    private function eventKey(CourierAccount $courierAccount, array $payload): string
    {
        return hash('sha256', implode('|', [
            'steadfast',
            $courierAccount->id,
            $payload['notification_type'] ?? '',
            $payload['tracking_id'] ?? '',
            $payload['consignment_id'] ?? '',
            $payload['invoice'] ?? '',
            $payload['status'] ?? '',
            $payload['updated_at'] ?? '',
        ]));
    }
}
