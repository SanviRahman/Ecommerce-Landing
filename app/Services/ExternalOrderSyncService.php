<?php

namespace App\Services;

use App\Models\ExternalOrderSync;
use App\Models\ExternalWebsite;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
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
        int $limit = 20
    ): array {
        $limit = max(1, min($limit, 100));
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        $this->localOrdersQuery()
            ->whereDoesntHave('externalOrderSyncs', function ($query) use ($externalWebsite): void {
                $query->where('external_website_id', $externalWebsite->id);
            })
            ->oldest('id')
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

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            ...$this->syncProgress($externalWebsite),
        ];
    }

    public function syncProgress(ExternalWebsite $externalWebsite): array
    {
        $totalEligible = (clone $this->localOrdersQuery())->count();

        $remaining = (clone $this->localOrdersQuery())
            ->whereDoesntHave('externalOrderSyncs', function ($query) use ($externalWebsite): void {
                $query->where('external_website_id', $externalWebsite->id);
            })
            ->count();

        $sentTotal = ExternalOrderSync::query()
            ->where('external_website_id', $externalWebsite->id)
            ->where('status', ExternalOrderSync::STATUS_SENT)
            ->whereHas('order', function ($query): void {
                $this->applyLocalOrderConstraint($query);
            })
            ->count();

        $failedTotal = ExternalOrderSync::query()
            ->where('external_website_id', $externalWebsite->id)
            ->where('status', ExternalOrderSync::STATUS_FAILED)
            ->whereHas('order', function ($query): void {
                $this->applyLocalOrderConstraint($query);
            })
            ->count();

        return [
            'total_eligible' => $totalEligible,
            'remaining' => $remaining,
            'sent_total' => $sentTotal,
            'failed_total' => $failedTotal,
        ];
    }

    public function refreshSyncedOrders(
        ExternalWebsite $externalWebsite,
        int $limit = 20,
        int $afterId = 0
    ): array {
        $limit = max(1, min($limit, 100));
        $afterId = max(0, $afterId);
        $refreshed = 0;
        $failed = 0;
        $lastProcessedId = $afterId;

        $baseQuery = $this->localOrdersQuery()
            ->whereHas('externalOrderSyncs', function ($query) use ($externalWebsite): void {
                $query
                    ->where('external_website_id', $externalWebsite->id)
                    ->where('status', ExternalOrderSync::STATUS_SENT);
            });

        $totalEligible = (clone $baseQuery)->count();

        $orders = (clone $baseQuery)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $orders->each(function (Order $order) use (
            $externalWebsite,
            &$refreshed,
            &$failed,
            &$lastProcessedId
        ): void {
            $lastProcessedId = max($lastProcessedId, (int) $order->id);
            $sync = $this->senderService->send($order, $externalWebsite, true);

            if ($sync->status === ExternalOrderSync::STATUS_SENT) {
                $refreshed++;
            } else {
                $failed++;
            }
        });

        $remaining = (clone $baseQuery)
            ->where('id', '>', $lastProcessedId)
            ->count();

        return [
            'refreshed' => $refreshed,
            'failed' => $failed,
            'total_eligible' => $totalEligible,
            'remaining' => $remaining,
            'next_cursor' => $lastProcessedId,
        ];
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

        return [
            'sent' => $sent,
            'failed' => $failed,
            ...$this->syncProgress($externalWebsite),
        ];
    }

    private function localOrdersQuery(): Builder
    {
        $query = Order::query();
        $this->applyLocalOrderConstraint($query);

        return $query;
    }

    private function applyLocalOrderConstraint(Builder $query): void
    {
        $query->where(function (Builder $createdViaQuery): void {
            $createdViaQuery
                ->whereNull('created_via')
                ->orWhere('created_via', '!=', Order::CREATED_VIA_EXTERNAL_API);
        });
    }
}
