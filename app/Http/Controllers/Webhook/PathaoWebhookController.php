<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Services\CourierWebhookLogger;
use App\Services\PathaoStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PathaoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        CourierAccount $courierAccount,
        PathaoStatusService $statusService,
        CourierWebhookLogger $webhookLogger
    ): JsonResponse {
        $secret = trim((string) $courierAccount->setting('pathao_webhook_secret'));

        if (
            strtolower((string) $courierAccount->code) !== 'pathao'
            || ! $courierAccount->status
            || ! $courierAccount->setting('webhook_enabled', false)
        ) {
            return $this->accepted(
                'Webhook ignored because this Pathao account is inactive.',
                $secret
            );
        }

        $providedSignature = trim((string) $request->header('X-PATHAO-Signature', ''));

        if (
            $secret === ''
            || $providedSignature === ''
            || ! hash_equals($secret, $providedSignature)
        ) {
            Log::warning('Pathao webhook signature verification failed.', [
                'courier_account_id' => $courierAccount->id,
                'signature_header_present' => $providedSignature !== '',
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status' => 401,
                'message' => 'Unauthorized.',
                'data' => null,
            ], 401);
        }

        $rawPayload = $request->all();
        $event = strtolower(trim((string) (
            data_get($rawPayload, 'event')
                ?: data_get($rawPayload, 'data.event')
        )));

        if ($event === 'webhook_integration') {
            return $this->accepted('Successfully accepted webhook integration.', $secret);
        }

        $payload = $this->normalizePayload($rawPayload, $statusService);
        $validator = Validator::make($payload, [
            'event' => ['required', 'string', 'max:150'],
            'external_event_id' => ['nullable', 'string', 'max:255'],
            'merchant_order_id' => ['nullable', 'string', 'max:255'],
            'consignment_id' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:100'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'updated_at' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            Log::warning('Pathao webhook payload validation failed.', [
                'courier_account_id' => $courierAccount->id,
                'errors' => $validator->errors()->toArray(),
                'payload_keys' => array_keys($rawPayload),
            ]);

            return $this->accepted(
                'Webhook payload accepted but ignored because required data was missing.',
                $secret,
                ['errors' => $validator->errors()->toArray()]
            );
        }

        $payload = $validator->validated();

        if (blank($payload['merchant_order_id']) && blank($payload['consignment_id'])) {
            Log::warning('Pathao webhook has no order reference.', [
                'courier_account_id' => $courierAccount->id,
                'event' => $payload['event'],
            ]);

            return $this->accepted(
                'Webhook accepted but ignored because no order reference was found.',
                $secret
            );
        }

        $eventKey = $this->eventKey($courierAccount, $payload);
        $log = $webhookLogger->start([
            'courier_account_id' => $courierAccount->id,
            'provider' => 'pathao',
            'source' => 'webhook',
            'event_key' => $eventKey,
            'event_name' => $payload['event'],
            'external_event_id' => $payload['external_event_id'],
            'consignment_id' => $payload['consignment_id'],
            'tracking_code' => $payload['consignment_id'],
            'invoice' => $payload['merchant_order_id'],
            'merchant_order_id' => $payload['merchant_order_id'],
            'courier_status' => $payload['status'],
            'mapped_status' => $statusService->category($payload['status']),
            'signature_valid' => true,
            'result' => 'received',
            'headers' => $webhookLogger->safeHeaders($request->headers->all()),
            'payload' => $rawPayload,
            'received_at' => now(),
        ]);

        if ($webhookLogger->completed($log)) {
            return $this->accepted('Webhook already handled.', $secret);
        }

        try {
            $order = $this->findOrder(
                $courierAccount,
                $payload['consignment_id'],
                $payload['merchant_order_id']
            );

            if (! $order) {
                $webhookLogger->ignored($log, 'Matching order not found.');

                return $this->accepted(
                    'Webhook accepted; matching order not found.',
                    $secret
                );
            }

            $updatedOrder = $statusService->apply(
                $order,
                $courierAccount,
                $payload['status'],
                $rawPayload,
                'webhook'
            );

            $webhookLogger->processed($log, [
                'order_id' => $updatedOrder->id,
            ]);

            return $this->accepted('Order status updated.', $secret);
        } catch (Throwable $exception) {
            report($exception);
            $webhookLogger->failed($log, $exception);

            Log::error('Pathao webhook processing failed.', [
                'courier_account_id' => $courierAccount->id,
                'event_key' => $eventKey,
                'consignment_id' => $payload['consignment_id'],
                'merchant_order_id' => $payload['merchant_order_id'],
                'message' => $exception->getMessage(),
            ]);

            return $this->accepted(
                'Webhook accepted; processing will be retried after the issue is resolved.',
                $secret
            );
        }
    }

    private function normalizePayload(
        array $payload,
        PathaoStatusService $statusService
    ): array {
        $event = $this->scalar(
            data_get($payload, 'event')
                ?: data_get($payload, 'data.event')
        );

        return [
            'event' => strtolower((string) $event),
            'external_event_id' => $this->scalar(
                data_get($payload, 'event_id')
                    ?: data_get($payload, 'id')
                    ?: data_get($payload, 'data.event_id')
                    ?: data_get($payload, 'data.id')
            ),
            'merchant_order_id' => $this->scalar(
                data_get($payload, 'merchant_order_id')
                    ?: data_get($payload, 'data.merchant_order_id')
            ),
            'consignment_id' => $this->scalar(
                data_get($payload, 'consignment_id')
                    ?: data_get($payload, 'data.consignment_id')
            ),
            'status' => $statusService->statusFromPayload($payload),
            'delivery_fee' => data_get($payload, 'delivery_fee')
                ?? data_get($payload, 'data.delivery_fee'),
            'updated_at' => $this->scalar(
                data_get($payload, 'updated_at')
                    ?: data_get($payload, 'created_at')
                    ?: data_get($payload, 'data.updated_at')
                    ?: data_get($payload, 'data.created_at')
            ),
        ];
    }

    private function findOrder(
        CourierAccount $courierAccount,
        ?string $consignmentId,
        ?string $merchantOrderId
    ): ?Order {
        return Order::query()
            ->where(function ($query) use ($consignmentId, $merchantOrderId) {
                if (filled($consignmentId)) {
                    $query->where('pathao_consignment_id', $consignmentId);
                }

                if (filled($merchantOrderId)) {
                    $method = filled($consignmentId) ? 'orWhere' : 'where';
                    $query->{$method}(function ($referenceQuery) use ($merchantOrderId) {
                        $referenceQuery
                            ->where('pathao_merchant_order_id', $merchantOrderId)
                            ->orWhere('invoice_id', $merchantOrderId);
                    });
                }
            })
            ->where(function ($query) use ($courierAccount) {
                $query->where('courier_account_id', $courierAccount->id)
                    ->orWhere(function ($fallback) {
                        $fallback->whereNull('courier_account_id')
                            ->where('courier_service', 'pathao');
                    });
            })
            ->latest('id')
            ->first();
    }

    private function accepted(
        string $message,
        string $secret,
        array $extra = []
    ): JsonResponse {
        $response = response()->json(array_merge([
            'status' => 202,
            'message' => $message,
            'data' => null,
        ], $extra), 202);

        if ($secret !== '') {
            $response->headers->set(
                'X-Pathao-Merchant-Webhook-Integration-Secret',
                $secret
            );
        }

        return $response;
    }

    private function eventKey(CourierAccount $courierAccount, array $payload): string
    {
        return hash('sha256', implode('|', [
            'pathao',
            $courierAccount->id,
            $payload['event'] ?? '',
            $payload['external_event_id'] ?? '',
            $payload['consignment_id'] ?? '',
            $payload['merchant_order_id'] ?? '',
            $payload['status'] ?? '',
            $payload['updated_at'] ?? '',
        ]));
    }

    private function scalar(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }
}
