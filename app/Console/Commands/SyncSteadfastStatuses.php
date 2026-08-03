<?php

namespace App\Console\Commands;

use App\Models\CourierAccount;
use App\Models\Order;
use App\Services\SteadfastCourierService;
use App\Services\SteadfastStatusService;
use Illuminate\Console\Command;
use Throwable;

class SyncSteadfastStatuses extends Command
{
    protected $signature = 'courier:sync-steadfast-statuses
        {--account= : Courier account ID}
        {--limit=100 : Maximum orders per account}
        {--force : Ignore configured sync interval and final courier status}';

    protected $description = 'Sync pending SteadFast courier delivery statuses using active dynamic courier accounts.';

    public function handle(
        SteadfastCourierService $courierService,
        SteadfastStatusService $statusService
    ): int {
        $limit = min(max((int) $this->option('limit'), 1), 500);
        $force = (bool) $this->option('force');

        $accounts = CourierAccount::query()
            ->active()
            ->where('code', 'steadfast')
            ->when(
                $this->option('account'),
                fn ($query) => $query->whereKey((int) $this->option('account'))
            )
            ->orderBy('id')
            ->get();

        if ($accounts->isEmpty()) {
            $this->warn('No active SteadFast courier account found.');

            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;
        $skippedAccounts = 0;

        foreach ($accounts as $account) {
            if (! $force && ! $statusService->statusSyncEnabled($account)) {
                $skippedAccounts++;
                continue;
            }

            $interval = $statusService->syncIntervalMinutes($account);

            $orders = Order::query()
                ->where('courier_account_id', $account->id)
                ->where('courier_service', 'steadfast')
                ->where(function ($query) {
                    $query->whereNotNull('steadfast_sent_at')
                        ->orWhereNotNull('steadfast_tracking_code')
                        ->orWhereNotNull('steadfast_consignment_id');
                })
                ->when(! $force, function ($query) use ($interval) {
                    $query->where(function ($staleQuery) use ($interval) {
                        $staleQuery->whereNull('steadfast_synced_at')
                            ->orWhere('steadfast_synced_at', '<=', now()->subMinutes($interval));
                    });

                    $query->where(function ($statusQuery) {
                        $statusQuery->whereNull('steadfast_status')
                            ->orWhereNotIn('steadfast_status', array_merge(
                                SteadfastStatusService::DELIVERED_STATUSES,
                                SteadfastStatusService::FINAL_CANCELLED_STATUSES
                            ));
                    });
                })
                ->oldest('steadfast_synced_at')
                ->limit($limit)
                ->get();

            foreach ($orders as $order) {
                try {
                    $courierService->syncStatus($order);
                    $success++;
                    $this->line('Synced: ' . $order->invoice_id);
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);
                    $this->error($order->invoice_id . ': ' . $exception->getMessage());
                }
            }
        }

        $this->info("SteadFast sync complete. Success: {$success}, Failed: {$failed}, Skipped accounts: {$skippedAccounts}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
