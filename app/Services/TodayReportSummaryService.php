<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class TodayReportSummaryService
{
    /**
     * Generate the daily report summary from one shared source of truth.
     *
     * Database timestamps are treated as UTC, while the report day follows
     * Order::displayTimezone() (Asia/Dhaka by default).
     */
    public function summary(array $filters = [], mixed $user = null): array
    {
        [$todayStart, $todayEnd] = $this->databaseWindow($filters);

        $baseQuery = $this->baseOrderQuery($filters, $user);

        /*
         * Every workflow card is a CURRENT-STATE metric.
         *
         * Historical timestamps/status logs remain available for audit and
         * for resolving the selected activity window, but they must never keep
         * an order counted in a previous workflow card after its current state
         * changes. Static Order List 1/2 are also exclusive holding buckets.
         */
        $workflowBaseQuery = $this->outsideStaticOrderLists(clone $baseQuery);

        $todayCreatedOrders = (clone $baseQuery)
            ->whereBetween('created_at', [$todayStart, $todayEnd]);

        $todayActivityOrders = $this->businessActivityQuery(
            clone $workflowBaseQuery,
            $todayStart,
            $todayEnd
        );

        $pendingOrders = (clone $workflowBaseQuery)
            ->where('order_status', Order::STATUS_PENDING)
            ->where(function (Builder $query) use ($todayStart, $todayEnd) {
                $this->applyStatusEventWindow(
                    $query,
                    Order::STATUS_PENDING,
                    $todayStart,
                    $todayEnd,
                    null,
                    true
                );
            });

        $newOrders = (clone $workflowBaseQuery)
            ->where('order_status', Order::STATUS_PROCESSING)
            ->whereBetween('created_at', [$todayStart, $todayEnd]);

        /*
         * Complete Order is counted only while the CURRENT status is Confirmed.
         * An old confirmed_at value or Confirmed status log cannot keep an
         * order counted after it moves to Complete Invoice, Shipped, Delivered,
         * Cancelled, Stock Out, Fake or a custom Order List.
         */
        $confirmedOrders = $this->currentStatusActivityQuery(
            clone $workflowBaseQuery,
            Order::STATUS_CONFIRMED,
            $todayStart,
            $todayEnd,
            'confirmed_at'
        );

        /*
         * Shipped and Delivered are current-state metrics inside the selected
         * activity window. Historical timestamps/status logs remain available
         * for audit, but must not keep an order counted after it is manually
         * moved back to Complete Invoice or another workflow status.
         */
        $shippedOrders = (clone $workflowBaseQuery)
            ->where('order_status', Order::STATUS_SHIPPED)
            ->where(function (Builder $query) use ($todayStart, $todayEnd) {
                $this->applyStatusEventWindow(
                    $query,
                    Order::STATUS_SHIPPED,
                    $todayStart,
                    $todayEnd,
                    'shipped_at'
                );
            });

        $deliveredOrders = (clone $workflowBaseQuery)
            ->where('order_status', Order::STATUS_DELIVERED)
            ->where(function (Builder $query) use ($todayStart, $todayEnd) {
                $this->applyStatusEventWindow(
                    $query,
                    Order::STATUS_DELIVERED,
                    $todayStart,
                    $todayEnd,
                    'delivered_at'
                );
            });

        $stockOutOrders = (clone $workflowBaseQuery)
            ->where('order_status', Order::STATUS_STOCK_OUT)
            ->where(function (Builder $query) use ($todayStart, $todayEnd) {
                $this->applyStatusEventWindow(
                    $query,
                    Order::STATUS_STOCK_OUT,
                    $todayStart,
                    $todayEnd,
                    null,
                    true
                );
            });

        $cancelledOrders = (clone $workflowBaseQuery)
            ->where('order_status', Order::STATUS_CANCELLED)
            ->where(function (Builder $query) use ($todayStart, $todayEnd) {
                $this->applyStatusEventWindow(
                    $query,
                    Order::STATUS_CANCELLED,
                    $todayStart,
                    $todayEnd,
                    'cancelled_at'
                );
            });

        $completedInvoices = $this->completedInvoiceQuery(
            clone $workflowBaseQuery,
            $todayStart,
            $todayEnd
        );

        /*
         * Delivery counts each currently Shipped/Delivered order once when its
         * matching activity happened inside the selected window. Restricting
         * the current status prevents stale shipped_at/delivered_at values or
         * historical status logs from counting an order moved backwards.
         */
        $deliveryActivity = (clone $workflowBaseQuery)
            ->whereIn('order_status', [
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED,
            ])
            ->where(function (Builder $activityQuery) use ($todayStart, $todayEnd) {
                $activityQuery
                    ->where(function (Builder $query) use ($todayStart, $todayEnd) {
                        $this->applyStatusEventWindow(
                            $query,
                            Order::STATUS_SHIPPED,
                            $todayStart,
                            $todayEnd,
                            'shipped_at'
                        );
                    })
                    ->orWhere(function (Builder $query) use ($todayStart, $todayEnd) {
                        $this->applyStatusEventWindow(
                            $query,
                            Order::STATUS_DELIVERED,
                            $todayStart,
                            $todayEnd,
                            'delivered_at'
                        );
                    });
            });

        $incompletedInvoiceQuery = clone $todayActivityOrders;

        if (Schema::hasColumn('orders', 'invoice_printed_at')) {
            $incompletedInvoiceQuery->whereNull('invoice_printed_at');
        } else {
            $incompletedInvoiceQuery->where(function (Builder $query) {
                $query
                    ->whereNull('payment_status')
                    ->orWhereNotIn('payment_status', ['paid', 'collected']);
            });
        }

        return [
            /*
             * Today's Order counts only valid orders created in the selected
             * Bangladesh calendar day. Old orders acted on today are counted
             * only in their own activity cards.
             */
            'todays_order' => $this->countDistinctOrders(
                (clone $todayCreatedOrders)->whereNotIn('order_status', [
                    Order::STATUS_CANCELLED,
                    Order::STATUS_FAKE,
                ])
            ),

            'new_order' => $this->countDistinctOrders($newOrders),

            'incompleted_order' => $this->countDistinctOrders(
                (clone $todayActivityOrders)->whereNotIn('order_status', [
                    Order::STATUS_DELIVERED,
                    Order::STATUS_CANCELLED,
                    Order::STATUS_FAKE,
                    Order::STATUS_STOCK_OUT,
                ])
            ),

            /*
             * Primary workflow cards are mutually exclusive current states.
             * Old event timestamps/logs are audit history only.
             */
            'completed_order' => $this->countDistinctOrders($confirmedOrders),
            'completed_invoice' => $this->countDistinctOrders($completedInvoices),
            'shipped_orders' => $this->countDistinctOrders($shippedOrders),
            'delivered_order' => $this->countDistinctOrders($deliveredOrders),
            'cancelled' => $this->countDistinctOrders($cancelledOrders),
            'pending_order' => $this->countDistinctOrders($pendingOrders),
            'stock_out_order' => $this->countDistinctOrders($stockOutOrders),

            'order_list_1' => $this->orderListMovedCount(
                clone $baseQuery,
                Order::CUSTOM_LIST_ONE,
                $todayStart,
                $todayEnd
            ),

            'order_list_2' => $this->orderListMovedCount(
                clone $baseQuery,
                Order::CUSTOM_LIST_TWO,
                $todayStart,
                $todayEnd
            ),

            'incompleted_invoice' => $this->countDistinctOrders($incompletedInvoiceQuery),

            'checkout' => $this->countDistinctOrders(
                (clone $todayCreatedOrders)->whereNotIn('order_status', [
                    Order::STATUS_CANCELLED,
                    Order::STATUS_FAKE,
                ])
            ),

            'delivery' => $this->countDistinctOrders($deliveryActivity),
        ];
    }

    /**
     * Apply Dashboard filters and employee data-access restriction once,
     * before building the individual report-card queries.
     */
    private function baseOrderQuery(array $filters, mixed $user): Builder
    {
        $query = Order::query();

        if (
            $user
            && method_exists($user, 'isEmployee')
            && $user->isEmployee()
            && Schema::hasColumn('orders', 'assigned_employee_id')
        ) {
            $query->where('assigned_employee_id', $user->id);
        }

        if (
            ! empty($filters['campaign_id'])
            && Schema::hasColumn('orders', 'campaign_id')
        ) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (
            ! empty($filters['order_status'])
            && Schema::hasColumn('orders', 'order_status')
        ) {
            $query->where('order_status', $filters['order_status']);
        }

        if (
            ! empty($filters['payment_status'])
            && Schema::hasColumn('orders', 'payment_status')
        ) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (
            ! empty($filters['delivery_area'])
            && Schema::hasColumn('orders', 'delivery_area')
        ) {
            $query->where('delivery_area', $filters['delivery_area']);
        }

        return $query;
    }

    /**
     * Resolve a local calendar range and convert it into the UTC range stored
     * in the database. "All Time" intentionally means today's summary here,
     * matching the current Dashboard/Report card business rule.
     */
    private function databaseWindow(array $filters): array
    {
        $timezone = method_exists(Order::class, 'displayTimezone')
            ? Order::displayTimezone()
            : config('app.order_display_timezone', 'Asia/Dhaka');

        $now = CarbonImmutable::now($timezone);
        $dateFilter = $filters['date_filter'] ?? 'all';

        [$localStart, $localEnd] = match ($dateFilter) {
            'yesterday' => [
                $now->subDay()->startOfDay(),
                $now->subDay()->endOfDay(),
            ],
            'this_week' => [
                $now->startOfWeek()->startOfDay(),
                $now->endOfWeek()->endOfDay(),
            ],
            'this_month' => [
                $now->startOfMonth()->startOfDay(),
                $now->endOfMonth()->endOfDay(),
            ],
            'last_month' => [
                $now->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                $now->subMonthNoOverflow()->endOfMonth()->endOfDay(),
            ],
            'this_year' => [
                $now->startOfYear()->startOfDay(),
                $now->endOfYear()->endOfDay(),
            ],
            'custom' => $this->customLocalWindow($filters, $timezone, $now),
            default => [
                $now->startOfDay(),
                $now->endOfDay(),
            ],
        };

        return [
            $localStart->utc(),
            $localEnd->utc(),
        ];
    }

    private function customLocalWindow(
        array $filters,
        string $timezone,
        CarbonImmutable $fallbackNow
    ): array {
        $startInput = $filters['start_date'] ?? null;
        $endInput = $filters['end_date'] ?? null;

        if (! $startInput && ! $endInput) {
            return [
                $fallbackNow->startOfDay(),
                $fallbackNow->endOfDay(),
            ];
        }

        try {
            $start = CarbonImmutable::parse(
                $startInput ?: $endInput,
                $timezone
            )->startOfDay();

            $end = CarbonImmutable::parse(
                $endInput ?: $startInput,
                $timezone
            )->endOfDay();
        } catch (\Throwable) {
            return [
                $fallbackNow->startOfDay(),
                $fallbackNow->endOfDay(),
            ];
        }

        if ($start->greaterThan($end)) {
            return [
                $end->startOfDay(),
                $start->endOfDay(),
            ];
        }

        return [$start, $end];
    }

    private function businessActivityQuery(
        Builder $baseQuery,
        $todayStart,
        $todayEnd
    ): Builder {
        $query = $baseQuery
            ->whereNotIn('order_status', [
                Order::STATUS_CANCELLED,
                Order::STATUS_FAKE,
            ])
            ->where(function (Builder $activityQuery) use ($todayStart, $todayEnd) {
                $activityQuery->whereBetween('created_at', [$todayStart, $todayEnd]);

                if (Schema::hasColumn('orders', 'shipped_at')) {
                    $activityQuery->orWhereBetween('shipped_at', [$todayStart, $todayEnd]);
                }

                if (Schema::hasColumn('orders', 'delivered_at')) {
                    $activityQuery->orWhereBetween('delivered_at', [$todayStart, $todayEnd]);
                }

                if (Schema::hasColumn('orders', 'invoice_printed_at')) {
                    $activityQuery->orWhereBetween('invoice_printed_at', [$todayStart, $todayEnd]);
                }

                if (Schema::hasTable('order_status_logs')) {
                    $activityQuery->orWhereHas(
                        'statusLogs',
                        function (Builder $statusLogQuery) use ($todayStart, $todayEnd) {
                            $statusLogQuery
                                ->whereIn('status', [
                                    Order::STATUS_SHIPPED,
                                    Order::STATUS_DELIVERED,
                                ])
                                ->whereBetween('created_at', [$todayStart, $todayEnd]);
                        }
                    );
                }
            });

        /*
         * A cancellation event during this same report window takes priority
         * over the broader activity cards.
         */
        if (Schema::hasColumn('orders', 'cancelled_at')) {
            $query->where(function (Builder $cancelQuery) use ($todayStart, $todayEnd) {
                $cancelQuery
                    ->whereNull('cancelled_at')
                    ->orWhereNotBetween('cancelled_at', [$todayStart, $todayEnd]);
            });
        }

        if (Schema::hasTable('order_status_logs')) {
            $query->whereDoesntHave(
                'statusLogs',
                function (Builder $statusLogQuery) use ($todayStart, $todayEnd) {
                    $statusLogQuery
                        ->where('status', Order::STATUS_CANCELLED)
                        ->whereBetween('created_at', [$todayStart, $todayEnd]);
                }
            );
        }

        return $query;
    }

    private function applyStatusEventWindow(
        Builder $query,
        string $status,
        $todayStart,
        $todayEnd,
        ?string $timestampColumn = null,
        bool $includeCreatedToday = false
    ): void {
        $hasCondition = false;

        if (
            $timestampColumn
            && Schema::hasColumn('orders', $timestampColumn)
        ) {
            $query->whereBetween($timestampColumn, [$todayStart, $todayEnd]);
            $hasCondition = true;
        }

        if (Schema::hasTable('order_status_logs')) {
            $method = $hasCondition ? 'orWhereHas' : 'whereHas';

            $query->{$method}(
                'statusLogs',
                function (Builder $statusLogQuery) use (
                    $status,
                    $todayStart,
                    $todayEnd
                ) {
                    $statusLogQuery
                        ->where('status', $status)
                        ->whereBetween('created_at', [$todayStart, $todayEnd]);
                }
            );

            $hasCondition = true;
        }

        if ($includeCreatedToday) {
            $method = $hasCondition ? 'orWhereBetween' : 'whereBetween';
            $query->{$method}('created_at', [$todayStart, $todayEnd]);
            $hasCondition = true;
        }

        if (! $hasCondition) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * Complete Invoice is a current workflow state, not a permanent event
     * counter. invoice_printed_at remains audit data only.
     */
    private function completedInvoiceQuery(
        Builder $baseQuery,
        $todayStart,
        $todayEnd
    ): Builder {
        return $this->currentStatusActivityQuery(
            $baseQuery,
            Order::STATUS_COMPLETE_INVOICE,
            $todayStart,
            $todayEnd,
            Schema::hasColumn('orders', 'invoice_printed_at')
                ? 'invoice_printed_at'
                : null
        );
    }

    /**
     * Build a mutually exclusive current-status metric inside a report window.
     *
     * The current order_status is always mandatory. Historical timestamps and
     * status logs are used only to establish activity time. updated_at and
     * created_at are safe fallbacks for legacy rows or a status revisited after
     * its original timestamp was already populated.
     */
    private function currentStatusActivityQuery(
        Builder $baseQuery,
        string $status,
        $todayStart,
        $todayEnd,
        ?string $timestampColumn = null
    ): Builder {
        $baseQuery->where('order_status', $status);

        return $baseQuery->where(
            function (Builder $activityQuery) use (
                $status,
                $todayStart,
                $todayEnd,
                $timestampColumn
            ) {
                $hasCondition = false;

                if (
                    $timestampColumn
                    && Schema::hasColumn('orders', $timestampColumn)
                ) {
                    $activityQuery->whereBetween(
                        $timestampColumn,
                        [$todayStart, $todayEnd]
                    );

                    $hasCondition = true;
                }

                if (Schema::hasTable('order_status_logs')) {
                    $method = $hasCondition ? 'orWhereHas' : 'whereHas';

                    $activityQuery->{$method}(
                        'statusLogs',
                        function (Builder $statusLogQuery) use (
                            $status,
                            $todayStart,
                            $todayEnd
                        ) {
                            $statusLogQuery
                                ->where('status', $status)
                                ->whereBetween(
                                    'created_at',
                                    [$todayStart, $todayEnd]
                                );
                        }
                    );

                    $hasCondition = true;
                }

                if (Schema::hasColumn('orders', 'updated_at')) {
                    $method = $hasCondition
                        ? 'orWhereBetween'
                        : 'whereBetween';

                    $activityQuery->{$method}(
                        'updated_at',
                        [$todayStart, $todayEnd]
                    );

                    $hasCondition = true;
                }

                if (Schema::hasColumn('orders', 'created_at')) {
                    $method = $hasCondition
                        ? 'orWhereBetween'
                        : 'whereBetween';

                    $activityQuery->{$method}(
                        'created_at',
                        [$todayStart, $todayEnd]
                    );

                    $hasCondition = true;
                }

                if (! $hasCondition) {
                    $activityQuery->whereRaw('1 = 0');
                }
            }
        );
    }

    private function orderListMovedCount(
        Builder $baseQuery,
        string $listName,
        $todayStart,
        $todayEnd
    ): int {
        if (! Schema::hasColumn('orders', 'custom_order_list')) {
            return 0;
        }

        $query = $baseQuery
            ->where('custom_order_list', $listName)
            ->where(function (Builder $movementQuery) use ($todayStart, $todayEnd) {
                if (Schema::hasColumn('orders', 'custom_order_list_moved_at')) {
                    $movementQuery
                        ->whereBetween(
                            'custom_order_list_moved_at',
                            [$todayStart, $todayEnd]
                        )
                        ->orWhere(
                            function (Builder $fallbackQuery) use (
                                $todayStart,
                                $todayEnd
                            ) {
                                $fallbackQuery
                                    ->whereNull('custom_order_list_moved_at')
                                    ->whereBetween(
                                        'updated_at',
                                        [$todayStart, $todayEnd]
                                    );
                            }
                        );

                    return;
                }

                $movementQuery->whereBetween(
                    'updated_at',
                    [$todayStart, $todayEnd]
                );
            });

        return $this->countDistinctOrders($query);
    }

    /**
     * Keep current workflow status cards separate from Static Order List 1/2.
     */
    private function outsideStaticOrderLists(Builder $query): Builder
    {
        if (Schema::hasColumn('orders', 'custom_order_list')) {
            $query->whereNull('custom_order_list');
        }

        return $query;
    }

    private function countDistinctOrders(Builder $query): int
    {
        return (int) $query
            ->select('orders.id')
            ->distinct()
            ->count('orders.id');
    }
}

