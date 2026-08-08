<?php

namespace App\Services;

use App\Models\ExternalOrderSync;
use App\Models\ExternalWebsite;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ExternalOrderSyncService
{
    public function __construct(
        private readonly ExternalOrderSenderService $senderService
    ) {
    }

    public function syncNewOrder(Order $order): Collection
    {
        if ($order->created_via === Order::CREATED_VIA_EXTERNAL_API) {
            return collect();
        }

        return ExternalWebsite::query()
            ->active()
            ->autoSending()
            ->orderBy('id')
            ->get()
            ->map(fn (ExternalWebsite $website): ExternalOrderSync =>
                $this->senderService->send($order, $website)
            );
    }

    public function syncOrderToWebsite(
        Order $order,
        ExternalWebsite $externalWebsite
    ): ExternalOrderSync {
        if (! $order->sync_uuid) {
            $order->forceFill([
                'sync_uuid' => (string) Str::uuid(),
            ])->saveQuietly();
        }

        return $this->senderService->send($order, $externalWebsite);
    }

    public function syncExistingOrders(
        ExternalWebsite $externalWebsite,
        int $limit = 100
    ): array {
        $limit = max(1, min($limit, 500));
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        Order::query()
            ->where(function ($query): void {
                $query
                    ->whereNull('created_via')
                    ->orWhere('created_via', '!=', Order::CREATED_VIA_EXTERNAL_API);
            })
            ->whereDoesntHave('externalOrderSyncs', function ($query) use ($externalWebsite): void {
                $query->where('external_website_id', $externalWebsite->id);
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(function (Order $order) use ($externalWebsite, &$sent, &$failed, &$skipped): void {
                $sync = $this->syncOrderToWebsite($order, $externalWebsite);

                match ($sync->status) {
                    ExternalOrderSync::STATUS_SENT => $sent++,
                    ExternalOrderSync::STATUS_SKIPPED => $skipped++,
                    default => $failed++,
                };
            });

        return compact('sent', 'failed', 'skipped');
    }

    public function retryFailedOrders(
        ExternalWebsite $externalWebsite,
        int $limit = 100
    ): array {
        $limit = max(1, min($limit, 500));
        $sent = 0;
        $failed = 0;

        ExternalOrderSync::query()
            ->with('order')
            ->where('external_website_id', $externalWebsite->id)
            ->where('status', ExternalOrderSync::STATUS_FAILED)
            ->oldest('last_attempted_at')
            ->limit($limit)
            ->get()
            ->each(function (ExternalOrderSync $sync) use ($externalWebsite, &$sent, &$failed): void {
                if (! $sync->order) {
                    $failed++;
                    return;
                }

                $result = $this->senderService->send($sync->order, $externalWebsite);

                if ($result->status === ExternalOrderSync::STATUS_SENT) {
                    $sent++;
                } else {
                    $failed++;
                }
            });

        return compact('sent', 'failed');
    }
}
