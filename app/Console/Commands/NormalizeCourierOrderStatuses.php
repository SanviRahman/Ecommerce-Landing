<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Services\PathaoStatusService;
use App\Services\SteadfastStatusService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NormalizeCourierOrderStatuses extends Command
{
    protected $signature = 'courier:normalize-order-statuses
        {--limit=5000 : Maximum courier orders to inspect}
        {--dry-run : Show changes without updating the database}';

    protected $description = 'Map existing courier provider states to Courier Pending/Courier Cancel while preserving local Cancelled.';

    public function handle(
        SteadfastStatusService $steadfastStatusService,
        PathaoStatusService $pathaoStatusService
    ): int {
        $limit = min(max((int) $this->option('limit'), 1), 50000);
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        Order::query()
            ->whereIn('courier_service', ['steadfast', 'pathao'])
            ->where(function (Builder $query): void {
                $query->whereNotNull('steadfast_status')
                    ->orWhereNotNull('pathao_status');
            })
            ->oldest('id')
            ->limit($limit)
            ->get()
            ->each(function (Order $order) use (
                $dryRun,
                $steadfastStatusService,
                $pathaoStatusService,
                &$updated,
                &$skipped,
                &$failed
            ): void {
                if (in_array($order->order_status, [
                    Order::STATUS_DELIVERED,
                    Order::STATUS_CANCELLED,
                    Order::STATUS_CANCELED,
                ], true)) {
                    $skipped++;
                    return;
                }

                $category = $order->courier_service === 'steadfast'
                    ? $steadfastStatusService->category($order->steadfast_status)
                    : $pathaoStatusService->category($order->pathao_status);

                $targetStatus = match ($category) {
                    'cancelled' => Order::STATUS_COURIER_CANCELLED,
                    'pending' => Order::STATUS_COURIER_PENDING,
                    default => null,
                };

                if (! $targetStatus || $order->order_status === $targetStatus) {
                    $skipped++;
                    return;
                }

                if ($dryRun) {
                    $this->line("[DRY RUN] {$order->invoice_id}: {$order->order_status} -> {$targetStatus}");
                    $updated++;
                    return;
                }

                try {
                    DB::transaction(function () use ($order, $targetStatus): void {
                        $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                        if (in_array($lockedOrder->order_status, [
                            Order::STATUS_DELIVERED,
                            Order::STATUS_CANCELLED,
                            Order::STATUS_CANCELED,
                        ], true)) {
                            return;
                        }

                        $lockedOrder->forceFill([
                            'order_status' => $targetStatus,
                        ])->saveQuietly();

                        if (Schema::hasTable('order_status_logs')) {
                            OrderStatusLog::query()->create([
                                'order_id' => $lockedOrder->id,
                                'status' => $targetStatus,
                                'note' => 'Courier status normalized from saved provider state.',
                                'created_by' => null,
                            ]);
                        }
                    });

                    $updated++;
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);
                    $this->error($order->invoice_id . ': ' . $exception->getMessage());
                }
            });

        $mode = $dryRun ? 'Dry run' : 'Update';
        $this->info("{$mode} complete. Updated: {$updated}, Skipped: {$skipped}, Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
