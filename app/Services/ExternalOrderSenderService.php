<?php

namespace App\Services;

use App\Models\ExternalOrderSync;
use App\Models\ExternalWebsite;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class ExternalOrderSenderService
{
    public function __construct(
        private readonly ExternalOrderPayloadBuilder $payloadBuilder
    ) {
    }

    public function send(Order $order, ExternalWebsite $externalWebsite, bool $force = false): ExternalOrderSync
    {
        $sync = ExternalOrderSync::query()->firstOrCreate(
            [
                'order_id' => $order->id,
                'external_website_id' => $externalWebsite->id,
            ],
            [
                'sync_uuid' => $order->sync_uuid,
                'status' => ExternalOrderSync::STATUS_PENDING,
            ]
        );

        if ($sync->status === ExternalOrderSync::STATUS_SENT && ! $force) {
            return $sync;
        }

        if ($order->created_via === Order::CREATED_VIA_EXTERNAL_API) {
            $sync->update([
                'status' => ExternalOrderSync::STATUS_SKIPPED,
                'error_message' => 'Imported orders are not sent again. This prevents an infinite sync loop.',
            ]);

            return $sync->fresh();
        }

        if (! $externalWebsite->canSendOrders()) {
            $sync->update([
                'status' => ExternalOrderSync::STATUS_FAILED,
                'attempts' => ((int) $sync->attempts) + 1,
                'last_attempted_at' => now(),
                'error_message' => 'Outgoing order sync is disabled or remote endpoint/token is missing.',
            ]);

            return $sync->fresh();
        }

        $sync->update([
            'status' => ExternalOrderSync::STATUS_SENDING,
            'attempts' => ((int) $sync->attempts) + 1,
            'last_attempted_at' => now(),
            'error_message' => null,
        ]);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) $externalWebsite->remote_api_token)
                ->timeout(max(3, (int) $externalWebsite->request_timeout))
                ->post(
                    (string) $externalWebsite->remote_order_endpoint,
                    $this->payloadBuilder->build($order)
                );

            $responseBody = $this->limitText($response->body());
            $responseData = $response->json();
            $successful = $response->successful()
                && (! is_array($responseData) || ($responseData['status'] ?? true) === true);

            if ($successful) {
                $sync->update([
                    'status' => ExternalOrderSync::STATUS_SENT,
                    'response_status' => $response->status(),
                    'response_body' => $responseBody,
                    'error_message' => null,
                    'sent_at' => now(),
                ]);

                $externalWebsite->forceFill([
                    'last_order_sent_at' => now(),
                    'last_send_error' => null,
                ])->saveQuietly();

                return $sync->fresh();
            }

            $message = is_array($responseData)
                ? (string) ($responseData['message'] ?? 'Remote website rejected the order.')
                : 'Remote website rejected the order.';

            $sync->update([
                'status' => ExternalOrderSync::STATUS_FAILED,
                'response_status' => $response->status(),
                'response_body' => $responseBody,
                'error_message' => $this->limitText($message),
            ]);

            $externalWebsite->forceFill([
                'last_send_failed_at' => now(),
                'last_send_error' => $this->limitText($message),
            ])->saveQuietly();
        } catch (ConnectionException $exception) {
            $this->markFailed($sync, $externalWebsite, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($sync, $externalWebsite, $exception->getMessage());
        }

        return $sync->fresh();
    }

    private function markFailed(
        ExternalOrderSync $sync,
        ExternalWebsite $externalWebsite,
        string $message
    ): void {
        $message = $this->limitText($message);

        $sync->update([
            'status' => ExternalOrderSync::STATUS_FAILED,
            'error_message' => $message,
        ]);

        $externalWebsite->forceFill([
            'last_send_failed_at' => now(),
            'last_send_error' => $message,
        ])->saveQuietly();
    }

    private function limitText(?string $value, int $limit = 6000): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}
