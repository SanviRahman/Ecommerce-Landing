<?php

namespace App\Console\Commands;

use App\Models\CourierAccount;
use App\Models\Order;
use App\Services\PathaoCourierService;
use App\Services\PathaoStatusService;
use Illuminate\Console\Command;
use Throwable;

class SyncPathaoStatuses extends Command
{
    protected $signature = 'courier:sync-pathao-statuses
        {--account= : Courier account ID}
        {--limit=100 : Maximum orders per account}
        {--force : Ignore configured sync interval and final courier status}';

    protected $description = 'Sync Pathao courier delivery statuses using active dynamic courier accounts.';

    public function handle(
        PathaoCourierService $courierService,
        PathaoStatusService $statusService
    ): int {
        $limit = min(max((int) $this->option('limit'), 1), 500);
        $force = (bool) $this->option('force');

        $accounts = CourierAccount::query()
            ->active()
            ->where('code', 'pathao')
            ->when(
                $this->option('account'),
                fn ($query) => $query->whereKey((int) $this->option('account'))
            )
            ->orderBy('id')
            ->get();

        if ($accounts->isEmpty()) {
            $this->warn('No active Pathao courier account found.');

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
                ->where(function ($accountQuery) use ($account) {
                    $accountQuery->where('courier_account_id', $account->id)
                        ->orWhere(function ($fallbackQuery) {
                            $fallbackQuery->whereNull('courier_account_id')
                                ->where('courier_service', 'pathao');
                        });
                })
                ->where('courier_service', 'pathao')
                ->whereNotNull('pathao_consignment_id')
                ->where('pathao_consignment_id', '!=', '')
                ->when(! $force, function ($query) use ($interval) {
                    $query->where(function ($staleQuery) use ($interval) {
                        $staleQuery->whereNull('pathao_synced_at')
                            ->orWhere('pathao_synced_at', '<=', now()->subMinutes($interval));
                    });

                    $query->where(function ($statusQuery) {
                        $statusQuery->whereNull('pathao_status')
                            ->orWhereNotIn('pathao_status', array_merge(
                                PathaoStatusService::DELIVERED_STATUSES,
                                PathaoStatusService::FINAL_CANCELLED_STATUSES
                            ));
                    });
                })
                ->oldest('pathao_synced_at')
                ->oldest('id')
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

        $this->info("Pathao sync complete. Success: {$success}, Failed: {$failed}, Skipped accounts: {$skippedAccounts}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
