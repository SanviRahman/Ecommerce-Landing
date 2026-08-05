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
        {--limit=20 : Maximum non-final orders per account}
        {--delay=1000 : Delay in milliseconds between Pathao API requests}
        {--force : Ignore the configured status sync interval}';

    protected $description = 'Sync Pathao statuses safely with throttling and rate-limit protection.';

    public function handle(
        PathaoCourierService $courierService,
        PathaoStatusService $statusService
    ): int {
        $limit = min(max((int) $this->option('limit'), 1), 100);
        $delayMilliseconds = min(max((int) $this->option('delay'), 250), 5000);
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
        $rateLimitedAccounts = 0;

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
                ->where(function ($statusQuery) {
                    $statusQuery->whereNull('pathao_status')
                        ->orWhereNotIn('pathao_status', array_merge(
                            PathaoStatusService::DELIVERED_STATUSES,
                            PathaoStatusService::FINAL_CANCELLED_STATUSES
                        ));
                })
                ->when(! $force, function ($query) use ($interval) {
                    $query->where(function ($staleQuery) use ($interval) {
                        $staleQuery->whereNull('pathao_synced_at')
                            ->orWhere('pathao_synced_at', '<=', now()->subMinutes($interval));
                    });
                })
                ->oldest('pathao_synced_at')
                ->oldest('id')
                ->limit($limit)
                ->get();

            foreach ($orders as $index => $order) {
                try {
                    $courierService->syncStatus($order);
                    $success++;
                    $this->line('Synced: ' . $order->invoice_id);
                } catch (Throwable $exception) {
                    $message = $exception->getMessage();

                    if ($this->isRateLimitError($message)) {
                        $rateLimitedAccounts++;
                        $this->warn(
                            'Pathao rate limit reached for account #'
                            . $account->id
                            . '. Remaining orders will be retried on the next run.'
                        );
                        break;
                    }

                    $failed++;
                    report($exception);
                    $this->error($order->invoice_id . ': ' . $message);
                }

                if ($delayMilliseconds > 0 && $index < ($orders->count() - 1)) {
                    usleep($delayMilliseconds * 1000);
                }
            }
        }

        $this->info(
            "Pathao sync complete. Success: {$success}, Failed: {$failed}, "
            . "Rate limited accounts: {$rateLimitedAccounts}, Skipped accounts: {$skippedAccounts}"
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isRateLimitError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'too many requests')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'http 429');
    }
}
