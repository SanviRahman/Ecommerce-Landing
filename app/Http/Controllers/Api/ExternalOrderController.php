<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreExternalOrderRequest;
use App\Models\ExternalWebsite;
use App\Services\ExternalOrderImportService;
use App\Services\ExternalOrderSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ExternalOrderController extends Controller
{
    /**
     * Allow a connected receiver website to ask this sender to recover
     * missing/failed outbound orders. Authentication uses the receiver token
     * already saved as this sender integration's remote_api_token.
     */
    public function manualSync(
        Request $request,
        ExternalOrderSyncService $syncService
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:progress,sync_missing,retry_failed'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'receiver_domain' => ['required', 'url:http,https', 'max:2000'],
        ]);

        $providedToken = trim((string) (
            $request->bearerToken()
            ?: $request->header('X-API-Token')
            ?: $request->header('X-API-Key')
        ));

        if ($providedToken === '') {
            return response()->json([
                'status' => false,
                'message' => 'Receiver token is required.',
            ], 401);
        }

        $receiverHost = $this->normalizeDomainHost((string) $validated['receiver_domain']);

        if ($receiverHost === '') {
            return response()->json([
                'status' => false,
                'message' => 'Receiver domain is invalid.',
            ], 422);
        }

        $externalWebsite = ExternalWebsite::query()
            ->active()
            ->sending()
            ->orderBy('id')
            ->get()
            ->first(function (ExternalWebsite $website) use ($providedToken, $receiverHost): bool {
                if (! $website->canSendOrders()) {
                    return false;
                }

                $websiteHost = $this->normalizeDomainHost((string) $website->domain);
                $endpointHost = $this->normalizeDomainHost((string) $website->remote_order_endpoint);

                if ($receiverHost !== $websiteHost && $receiverHost !== $endpointHost) {
                    return false;
                }

                try {
                    $remoteToken = trim((string) $website->remote_api_token);
                } catch (Throwable) {
                    return false;
                }

                return $remoteToken !== '' && hash_equals($remoteToken, $providedToken);
            });

        if (! $externalWebsite) {
            return response()->json([
                'status' => false,
                'message' => 'No connected outgoing website matches this receiver token/domain.',
            ], 401);
        }

        try {
            $action = (string) $validated['action'];
            $limit = (int) ($validated['limit'] ?? ($action === 'retry_failed' ? 100 : 20));

            $result = match ($action) {
                'sync_missing' => $syncService->syncExistingOrders(
                    $externalWebsite,
                    min($limit, 100)
                ),
                'retry_failed' => $syncService->retryFailedOrders(
                    $externalWebsite,
                    min($limit, 500)
                ),
                default => $syncService->syncProgress($externalWebsite),
            };

            return response()->json([
                'status' => true,
                'message' => match ($action) {
                    'sync_missing' => 'Missing order recovery batch completed.',
                    'retry_failed' => 'Failed order retry batch completed.',
                    default => 'Website order sync progress loaded.',
                },
                'data' => $result,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Manual website sync failed on the sender website.',
            ], 500);
        }
    }

    private function normalizeDomainHost(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            $host = parse_url('https://' . ltrim($value, '/'), PHP_URL_HOST);
        }

        $host = strtolower(trim((string) $host));

        return preg_replace('/^www\./i', '', $host) ?: '';
    }

    public function connectionRequest(
        Request $request,
        ExternalWebsite $externalWebsite
    ): JsonResponse {
        $validated = $request->validate([
            'source_website_name' => ['nullable', 'string', 'max:255'],
            'source_website_domain' => ['nullable', 'url:http,https', 'max:2000'],
        ]);

        $alreadyApproved = $externalWebsite->isInboundApproved();

        if (! $alreadyApproved) {
            $externalWebsite->forceFill([
                'inbound_approval_status' => ExternalWebsite::INBOUND_APPROVAL_PENDING,
                'inbound_request_received_at' => now(),
                'inbound_request_ip' => $request->ip(),
                'inbound_request_meta' => [
                    'source_website_name' => $validated['source_website_name'] ?? null,
                    'source_website_domain' => $validated['source_website_domain'] ?? null,
                    'user_agent' => $request->userAgent(),
                ],
                'inbound_rejected_at' => null,
            ])->saveQuietly();
        }

        return response()->json([
            'status' => true,
            'approved' => $alreadyApproved,
            'message' => $alreadyApproved
                ? 'Connection is already approved.'
                : 'Connection request received. Please approve it from the receiver admin panel.',
        ], $alreadyApproved ? 200 : 202);
    }

    public function status(ExternalWebsite $externalWebsite): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Bidirectional order API credentials are valid.',
            'data' => [
                'website' => $externalWebsite->name,
                'slug' => $externalWebsite->slug,
                'receiver' => config('app.name'),
                'receive_orders' => (bool) $externalWebsite->receive_orders,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    public function store(
        StoreExternalOrderRequest $request,
        ExternalWebsite $externalWebsite,
        ExternalOrderImportService $externalOrderImportService
    ): JsonResponse {
        try {
            $result = $externalOrderImportService->import(
                $externalWebsite,
                $request->validated(),
                $request->ip()
            );

            $order = $result['order'];
            $created = (bool) $result['created'];

            return response()->json([
                'status' => true,
                'created' => $created,
                'message' => $created
                    ? 'Order imported successfully.'
                    : 'This external order was already imported.',
                'data' => [
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice_id,
                    'external_order_id' => $order->external_order_id,
                    'sync_uuid' => $order->sync_uuid,
                    'order_status' => $order->order_status,
                    'assigned_employee_id' => $order->assigned_employee_id,
                ],
            ], $created ? 201 : 200);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Order import failed. Please check the payload and server log.',
            ], 500);
        }
    }
}
