<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Services\PathaoStatusService;
use App\Services\SteadfastStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SeparateCourierCancelledOrders extends Command
{
    protected $signature = 'courier:separate-cancelled-orders
        {--apply : Apply the repair instead of showing a dry-run report}
        {--limit=500 : Maximum orders to inspect}';

    protected $description = 'Separate courier-cancelled statuses from manual local Cancelled Orders.';

    public function handle(): int
    {
        $limit = min(max((int) $this->option('limit'), 1), 5000);
        $apply = (bool) $this->option('apply');

        $orders = $this->candidateQuery()
            ->with(['statusLogs' => fn ($query) => $query->oldest('created_at')->oldest('id')])
            ->oldest('id')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Courier cancel separation complete. Candidates: 0, Restored: 0, Preserved manual: 0, Failed: 0');

            return self::SUCCESS;
        }

        $restored = 0;
        $preservedManual = 0;
        $failed = 0;

        foreach ($orders as $order) {
            if ($this->isCurrentManualCancellation($order)) {
                $preservedManual++;
                $this->line('Preserved manual cancellation: ' . $order->invoice_id);
                continue;
            }

            $targetStatus = $this->previousWorkflowStatus($order);

            if (! $apply) {
                $this->line(
                    '[DRY RUN] ' . $order->invoice_id
                    . ' would move from cancelled to ' . $targetStatus
                );
                $restored++;
                continue;
            }

            try {
                DB::transaction(function () use ($order, $targetStatus): void {
                    $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                    $lockedOrder->forceFill([
                        'order_status' => $targetStatus,
                        'cancelled_at' => null,
                    ])->saveQuietly();

                    if (Schema::hasTable('order_status_logs')) {
                        OrderStatusLog::query()->create([
                            'order_id' => $lockedOrder->id,
                            'status' => $targetStatus,
                            'note' => 'Local status restored because courier cancellation is tracked separately.',
                            'created_by' => null,
                        ]);
                    }
                });

                $restored++;
                $this->line('Restored: ' . $order->invoice_id . ' -> ' . $targetStatus);
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->error($order->invoice_id . ': ' . $exception->getMessage());
            }
        }

        $mode = $apply ? 'complete' : 'dry run complete';
        $this->info(
            "Courier cancel separation {$mode}. Candidates: {$orders->count()}, "
            . "Restored: {$restored}, Preserved manual: {$preservedManual}, Failed: {$failed}"
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function candidateQuery(): Builder
    {
        return Order::query()
            ->whereIn('order_status', [
                Order::STATUS_CANCELLED,
                Order::STATUS_CANCELED,
            ])
            ->where(function (Builder $courierQuery): void {
                $courierQuery
                    ->where(function (Builder $steadfastQuery): void {
                        $steadfastQuery
                            ->where('courier_service', 'steadfast')
                            ->whereIn(
                                'steadfast_status',
                                SteadfastStatusService::CANCELLED_STATUSES
                            );
                    })
                    ->orWhere(function (Builder $pathaoQuery): void {
                        $pathaoQuery
                            ->where('courier_service', 'pathao')
                            ->whereIn(
                                'pathao_status',
                                PathaoStatusService::CANCELLED_STATUSES
                            );
                    });
            });
    }

    private function isCurrentManualCancellation(Order $order): bool
    {
        $manualCancelledAt = $this->latestManualCancellationNoteTime($order);

        if (! $manualCancelledAt) {
            return false;
        }

        $latestNonCancelledLog = $order->statusLogs
            ->filter(fn (OrderStatusLog $log) => ! in_array(
                (string) $log->status,
                [Order::STATUS_CANCELLED, Order::STATUS_CANCELED],
                true
            ))
            ->sortByDesc(fn (OrderStatusLog $log) => sprintf(
                '%s-%020d',
                optional($log->created_at)->format('Y-m-d H:i:s.u') ?: '',
                $log->id
            ))
            ->first();

        if (
            $latestNonCancelledLog?->created_at
            && $latestNonCancelledLog->created_at->greaterThan($manualCancelledAt)
        ) {
            return false;
        }

        $latestCancelledLog = $order->statusLogs
            ->filter(fn (OrderStatusLog $log) => in_array(
                (string) $log->status,
                [Order::STATUS_CANCELLED, Order::STATUS_CANCELED],
                true
            ))
            ->sortByDesc(fn (OrderStatusLog $log) => sprintf(
                '%s-%020d',
                optional($log->created_at)->format('Y-m-d H:i:s.u') ?: '',
                $log->id
            ))
            ->first();

        $comparisonTime = $latestCancelledLog?->created_at ?: $order->cancelled_at;

        return $comparisonTime
            && abs($comparisonTime->diffInMinutes($manualCancelledAt, false)) <= 10;
    }

    private function latestManualCancellationNoteTime(Order $order): ?CarbonImmutable
    {
        $note = (string) $order->admin_note;

        if ($note === '') {
            return null;
        }

        preg_match_all(
            '/Order cancelled by .*? on (\d{2} [A-Za-z]{3} \d{4}) at (\d{2}:\d{2} [AP]M)\./i',
            $note,
            $matches,
            PREG_SET_ORDER
        );

        if ($matches === []) {
            return null;
        }

        $match = end($matches);

        try {
            return CarbonImmutable::createFromFormat(
                'd M Y h:i A',
                $match[1] . ' ' . $match[2],
                Order::displayTimezone()
            )->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function previousWorkflowStatus(Order $order): string
    {
        $validStatuses = [
            Order::STATUS_SHIPPED,
            Order::STATUS_COMPLETE_INVOICE,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PENDING,
            Order::STATUS_PROCESSING,
            Order::STATUS_STOCK_OUT,
            Order::STATUS_FAKE,
        ];

        $previousLog = $order->statusLogs
            ->filter(fn (OrderStatusLog $log) => in_array(
                (string) $log->status,
                $validStatuses,
                true
            ))
            ->sortByDesc(fn (OrderStatusLog $log) => sprintf(
                '%s-%020d',
                optional($log->created_at)->format('Y-m-d H:i:s.u') ?: '',
                $log->id
            ))
            ->first();

        if ($previousLog) {
            return (string) $previousLog->status;
        }

        if ($order->shipped_at) {
            return Order::STATUS_SHIPPED;
        }

        if ($order->invoice_printed_at) {
            return Order::STATUS_COMPLETE_INVOICE;
        }

        if ($order->confirmed_at) {
            return Order::STATUS_CONFIRMED;
        }

        return Order::STATUS_PROCESSING;
    }
}
