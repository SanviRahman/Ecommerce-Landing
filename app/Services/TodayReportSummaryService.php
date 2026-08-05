<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class TodayReportSummaryService
{
    /**
     * Generate the dashboard daily summary from one shared source of truth.
     *
     * Business rules:
     * - The report day follows Bangladesh local date, then converts to database UTC.
     * - Every summary card is restricted to orders created inside the selected date window.
     * - A later courier sync or status change on an older order must not make that old
     *   order appear in today's Total Orders, Shipped, Delivered or other daily cards.
     * - Checkout counts only frontend orders created in the selected day.
     * - Manual orders are excluded from Checkout, shown in Manual Order, and included
     *   in Total Orders.
     * - Shipped uses the cumulative shipped/courier lifecycle, but only for orders
     *   created inside the selected date window.
     */
    public function summary(array $filters = [], mixed $user = null): array
    {
        [$todayStart, $todayEnd] = $this->databaseWindow($filters);

        $baseQuery = $this->baseOrderQuery($filters, $user);
        $workflowBaseQuery = $this->outsideStaticOrderLists(clone $baseQuery);

        $createdOrders = (clone $baseQuery)
            ->whereBetween('orders.created_at', [$todayStart, $todayEnd]);

        $workflowCreatedOrders = (clone $workflowBaseQuery)
            ->whereBetween('orders.created_at', [$todayStart, $todayEnd]);

        /*
         * Today's report is a creation-date report. Old orders updated today by a
         * webhook, courier sync, invoice action or manual status change are excluded.
         */
        $totalOrdersQuery = clone $createdOrders;
        $totalWorkflowOrdersQuery = clone $workflowCreatedOrders;

        if (Schema::hasColumn('orders', 'order_status')) {
            $totalOrdersQuery->where('orders.order_status', '!=', Order::STATUS_FAKE);
            $totalWorkflowOrdersQuery->where('orders.order_status', '!=', Order::STATUS_FAKE);
        }

        $newOrders = (clone $workflowCreatedOrders)
            ->where('orders.order_status', Order::STATUS_PROCESSING);

        $pendingOrders = (clone $workflowCreatedOrders)
            ->where('orders.order_status', Order::STATUS_PENDING);

        $confirmedOrders = (clone $workflowCreatedOrders)
            ->where('orders.order_status', Order::STATUS_CONFIRMED);

        $completedInvoices = (clone $workflowCreatedOrders)
            ->where('orders.order_status', $this->completeInvoiceStatus());

        $shippedOrders = (clone $workflowCreatedOrders)->shipped();

        $deliveredOrders = (clone $workflowCreatedOrders)
            ->where('orders.order_status', Order::STATUS_DELIVERED);

        /*
         * All status cards use the current status/lifecycle of orders created in
         * the selected day. Status changes on older orders are intentionally ignored.
         */
        $cancelledOrders = (clone $workflowCreatedOrders)
            ->whereIn('orders.order_status', $this->cancelledStatuses());

        $stockOutOrders = (clone $workflowCreatedOrders)
            ->where('orders.order_status', Order::STATUS_STOCK_OUT);

        $deliveryActivity = (clone $workflowCreatedOrders)
            ->whereIn('orders.order_status', [
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERED,
            ]);

        $incompletedOrderQuery = clone $totalWorkflowOrdersQuery;
        $incompletedOrderQuery->whereNotIn('orders.order_status', array_merge(
            $this->cancelledStatuses(),
            [
                Order::STATUS_DELIVERED,
                Order::STATUS_FAKE,
                Order::STATUS_STOCK_OUT,
            ]
        ));

        $incompletedInvoiceQuery = clone $totalWorkflowOrdersQuery;
        $incompletedInvoiceQuery->whereNotIn('orders.order_status', array_merge(
            $this->cancelledStatuses(),
            [
                Order::STATUS_FAKE,
            ]
        ));

        if (Schema::hasColumn('orders', 'invoice_printed_at')) {
            $incompletedInvoiceQuery->whereNull('orders.invoice_printed_at');
        } else {
            $incompletedInvoiceQuery->where(function (Builder $query) {
                $query
                    ->whereNull('orders.payment_status')
                    ->orWhereNotIn('orders.payment_status', ['paid', 'collected']);
            });
        }

        $checkoutOrders = $this->frontendCheckoutCreatedQuery(
            clone $baseQuery,
            $todayStart,
            $todayEnd
        );

        $manualOrders = $this->manualOrderCreatedQuery(
            clone $baseQuery,
            $todayStart,
            $todayEnd
        );

        return [
            'todays_order' => $this->countDistinctOrders($totalOrdersQuery),
            'new_order' => $this->countDistinctOrders($newOrders),
            'incompleted_order' => $this->countDistinctOrders($incompletedOrderQuery),
            'completed_order' => $this->countDistinctOrders($confirmedOrders),
            'completed_invoice' => $this->countDistinctOrders($completedInvoices),
            'shipped_orders' => $this->countDistinctOrders($shippedOrders),
            'delivered_order' => $this->countDistinctOrders($deliveredOrders),
            'cancelled' => $this->countDistinctOrders($cancelledOrders),
            'pending_order' => $this->countDistinctOrders($pendingOrders),
            'stock_out_order' => $this->countDistinctOrders($stockOutOrders),

            'order_list_1' => Schema::hasColumn('orders', 'custom_order_list')
                ? $this->countDistinctOrders(
                    (clone $createdOrders)->where(
                        'orders.custom_order_list',
                        Order::CUSTOM_LIST_ONE
                    )
                )
                : 0,

            'order_list_2' => Schema::hasColumn('orders', 'custom_order_list')
                ? $this->countDistinctOrders(
                    (clone $createdOrders)->where(
                        'orders.custom_order_list',
                        Order::CUSTOM_LIST_TWO
                    )
                )
                : 0,

            'incompleted_invoice' => $this->countDistinctOrders($incompletedInvoiceQuery),
            'checkout' => $this->countDistinctOrders($checkoutOrders),
            'manual_order' => $this->countDistinctOrders($manualOrders),
            'delivery' => $this->countDistinctOrders($deliveryActivity),
        ];
    }

    private function baseOrderQuery(array $filters, mixed $user): Builder
    {
        $query = Order::query();

        /*
         * Employee login must always see only his/her assigned orders.
         * Admin can additionally request a single user row or the Unassigned row
         * from DashboardController's User Order Report.
         */
        if (
            $user
            && method_exists($user, 'isEmployee')
            && $user->isEmployee()
            && Schema::hasColumn('orders', 'assigned_employee_id')
        ) {
            $query->where('orders.assigned_employee_id', $user->id);
        }

        if (! empty($filters['report_user_id'])) {
            $reportUserId = (int) $filters['report_user_id'];

            if (Schema::hasColumn('orders', 'assigned_employee_id')) {
                $query->where('orders.assigned_employee_id', $reportUserId);
            } elseif (Schema::hasColumn('orders', 'user_id')) {
                $query->where('orders.user_id', $reportUserId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (! empty($filters['report_unassigned'])) {
            if (Schema::hasColumn('orders', 'assigned_employee_id')) {
                $query->whereNull('orders.assigned_employee_id');
            } elseif (Schema::hasColumn('orders', 'user_id')) {
                $query->whereNull('orders.user_id');
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (! empty($filters['campaign_id']) && Schema::hasColumn('orders', 'campaign_id')) {
            $query->where('orders.campaign_id', $filters['campaign_id']);
        }

        if (! empty($filters['order_status']) && Schema::hasColumn('orders', 'order_status')) {
            $query->where('orders.order_status', $filters['order_status']);
        }

        if (! empty($filters['payment_status']) && Schema::hasColumn('orders', 'payment_status')) {
            $query->where('orders.payment_status', $filters['payment_status']);
        }

        if (! empty($filters['delivery_area']) && Schema::hasColumn('orders', 'delivery_area')) {
            $query->where('orders.delivery_area', $filters['delivery_area']);
        }

        return $query;
    }

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
            'last_week' => [
                $now->subDays(7)->startOfDay(),
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

    /**
     * Total Orders = created today + old orders that got a positive workflow
     * activity today. Checkout remains created-today frontend only.
     */
    private function reportTotalOrdersQuery(
        Builder $baseQuery,
        $todayStart,
        $todayEnd
    ): Builder {
        $positiveStatuses = $this->positiveActivityStatuses();

        $baseQuery->where(function (Builder $query) use ($todayStart, $todayEnd, $positiveStatuses) {
            $query->whereBetween('orders.created_at', [$todayStart, $todayEnd])
                ->orWhere(function (Builder $positiveQuery) use ($todayStart, $todayEnd, $positiveStatuses) {
                    $positiveQuery->whereIn('orders.order_status', $positiveStatuses);

                    $this->applyStatusActivityCondition(
                        $positiveQuery,
                        $positiveStatuses,
                        $todayStart,
                        $todayEnd,
                        [
                            Order::STATUS_CONFIRMED      => 'confirmed_at',
                            $this->completeInvoiceStatus() => 'invoice_printed_at',
                            Order::STATUS_SHIPPED        => 'shipped_at',
                            Order::STATUS_DELIVERED      => 'delivered_at',
                        ],
                        false
                    );
                });
        });

        if (Schema::hasColumn('orders', 'order_status')) {
            $baseQuery->where('orders.order_status', '!=', Order::STATUS_FAKE);
        }

        return $baseQuery;
    }

    private function currentStatusActivityQuery(
        Builder $baseQuery,
        array $statuses,
        $todayStart,
        $todayEnd,
        array $timestampColumnsByStatus = []
    ): Builder {
        $baseQuery->whereIn('orders.order_status', $statuses);

        $this->applyStatusActivityCondition(
            $baseQuery,
            $statuses,
            $todayStart,
            $todayEnd,
            $timestampColumnsByStatus,
            true
        );

        return $baseQuery;
    }

    /**
     * Activity check uses created_at, dedicated status timestamps and
     * order_status_logs. updated_at is only a final legacy fallback when no
     * reliable activity source exists; it is not used while logs/timestamps exist.
     */
    private function applyStatusActivityCondition(
        Builder $query,
        array $statuses,
        $todayStart,
        $todayEnd,
        array $timestampColumnsByStatus = [],
        bool $includeCreatedToday = true
    ): void {
        $query->where(function (Builder $activityQuery) use (
            $statuses,
            $todayStart,
            $todayEnd,
            $timestampColumnsByStatus,
            $includeCreatedToday
        ) {
            $hasReliableCondition = false;

            if ($includeCreatedToday && Schema::hasColumn('orders', 'created_at')) {
                $activityQuery->whereBetween('orders.created_at', [$todayStart, $todayEnd]);
                $hasReliableCondition = true;
            }

            foreach ($timestampColumnsByStatus as $status => $column) {
                if (! $column || ! Schema::hasColumn('orders', $column)) {
                    continue;
                }

                $method = $hasReliableCondition ? 'orWhere' : 'where';

                $activityQuery->{$method}(function (Builder $timestampQuery) use (
                    $status,
                    $column,
                    $todayStart,
                    $todayEnd
                ) {
                    $timestampQuery
                        ->where('orders.order_status', $status)
                        ->whereBetween('orders.' . $column, [$todayStart, $todayEnd]);
                });

                $hasReliableCondition = true;
            }

            if (Schema::hasTable('order_status_logs')) {
                $method = $hasReliableCondition ? 'orWhereHas' : 'whereHas';
                $logStatuses = $this->statusLogAliases($statuses);

                $activityQuery->{$method}(
                    'statusLogs',
                    function (Builder $statusLogQuery) use ($logStatuses, $todayStart, $todayEnd) {
                        $statusLogQuery
                            ->whereIn('status', $logStatuses)
                            ->whereBetween('created_at', [$todayStart, $todayEnd]);

                        if (Schema::hasColumn('order_status_logs', 'deleted_at')) {
                            $statusLogQuery->whereNull('deleted_at');
                        }
                    }
                );

                $hasReliableCondition = true;
            }

            if (! $hasReliableCondition && Schema::hasColumn('orders', 'updated_at')) {
                $activityQuery->whereBetween('orders.updated_at', [$todayStart, $todayEnd]);
                $hasReliableCondition = true;
            }

            if (! $hasReliableCondition) {
                $activityQuery->whereRaw('1 = 0');
            }
        });
    }

    private function manualOrderCreatedQuery(
        Builder $baseQuery,
        $todayStart,
        $todayEnd
    ): Builder {
        $baseQuery->whereBetween('orders.created_at', [$todayStart, $todayEnd]);

        if (Schema::hasColumn('orders', 'order_status')) {
            $baseQuery->where('orders.order_status', '!=', Order::STATUS_FAKE);
        }

        if (Schema::hasColumn('orders', 'created_via')) {
            $manualValues = $this->createdViaManualValues();

            $baseQuery->where(function (Builder $query) use ($manualValues) {
                $query->whereIn('orders.created_via', $manualValues);

                if (Schema::hasColumn('orders', 'created_by_admin_id')) {
                    $query->orWhereNotNull('orders.created_by_admin_id');
                }
            });

            return $baseQuery;
        }

        if (Schema::hasColumn('orders', 'created_by_admin_id')) {
            $baseQuery->whereNotNull('orders.created_by_admin_id');
            return $baseQuery;
        }

        if (Schema::hasColumn('orders', 'source_url')) {
            $baseQuery->where(function (Builder $query) {
                $query
                    ->where('orders.source_url', 'like', '%/admin/orders/create%')
                    ->orWhere('orders.source_url', 'like', '%admin/orders/create%');
            });

            return $baseQuery;
        }

        return $baseQuery->whereRaw('1 = 0');
    }

    private function frontendCheckoutCreatedQuery(
        Builder $baseQuery,
        $todayStart,
        $todayEnd
    ): Builder {
        $baseQuery->whereBetween('orders.created_at', [$todayStart, $todayEnd]);

        if (Schema::hasColumn('orders', 'order_status')) {
            $baseQuery->where('orders.order_status', '!=', Order::STATUS_FAKE);
        }

        if (Schema::hasColumn('orders', 'created_via')) {
            $frontendValue = $this->createdViaFrontendValue();

            $baseQuery->where(function (Builder $query) use ($frontendValue) {
                $query
                    ->whereNull('orders.created_via')
                    ->orWhere('orders.created_via', $frontendValue);
            });

            return $baseQuery;
        }

        if (Schema::hasColumn('orders', 'created_by_admin_id')) {
            $baseQuery->whereNull('orders.created_by_admin_id');
            return $baseQuery;
        }

        if (Schema::hasColumn('orders', 'source_url')) {
            $baseQuery->where(function (Builder $query) {
                $query
                    ->whereNull('orders.source_url')
                    ->orWhere(function (Builder $urlQuery) {
                        $urlQuery
                            ->where('orders.source_url', 'not like', '%/admin/orders/create%')
                            ->where('orders.source_url', 'not like', '%admin/orders/create%');
                    });
            });
        }

        return $baseQuery;
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
            ->where('orders.custom_order_list', $listName)
            ->where(function (Builder $movementQuery) use ($todayStart, $todayEnd) {
                if (Schema::hasColumn('orders', 'custom_order_list_moved_at')) {
                    $movementQuery
                        ->whereBetween('orders.custom_order_list_moved_at', [$todayStart, $todayEnd])
                        ->orWhere(function (Builder $fallbackQuery) use ($todayStart, $todayEnd) {
                            $fallbackQuery
                                ->whereNull('orders.custom_order_list_moved_at')
                                ->whereBetween('orders.updated_at', [$todayStart, $todayEnd]);
                        });

                    return;
                }

                $movementQuery->whereBetween('orders.updated_at', [$todayStart, $todayEnd]);
            });

        return $this->countDistinctOrders($query);
    }

    private function outsideStaticOrderLists(Builder $query): Builder
    {
        if (Schema::hasColumn('orders', 'custom_order_list')) {
            $query->whereNull('orders.custom_order_list');
        }

        return $query;
    }

    private function positiveActivityStatuses(): array
    {
        return [
            Order::STATUS_CONFIRMED,
            $this->completeInvoiceStatus(),
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
        ];
    }

    private function cancelledStatuses(): array
    {
        return array_values(array_unique([
            Order::STATUS_CANCELLED,
            'canceled',
        ]));
    }

    private function completeInvoiceStatus(): string
    {
        return defined(Order::class . '::STATUS_COMPLETE_INVOICE')
            ? constant(Order::class . '::STATUS_COMPLETE_INVOICE')
            : 'complete_invoice';
    }

    private function createdViaFrontendValue(): string
    {
        return defined(Order::class . '::CREATED_VIA_FRONTEND')
            ? constant(Order::class . '::CREATED_VIA_FRONTEND')
            : 'frontend';
    }

    private function createdViaManualValues(): array
    {
        $values = [];

        if (defined(Order::class . '::CREATED_VIA_ADMIN_MANUAL')) {
            $values[] = constant(Order::class . '::CREATED_VIA_ADMIN_MANUAL');
        }

        if (defined(Order::class . '::CREATED_VIA_EMPLOYEE_MANUAL')) {
            $values[] = constant(Order::class . '::CREATED_VIA_EMPLOYEE_MANUAL');
        }

        $values[] = 'admin_manual';
        $values[] = 'employee_manual';
        $values[] = 'manual';
        $values[] = 'admin';
        $values[] = 'employee';

        return array_values(array_unique(array_filter($values)));
    }

    private function statusLogAliases(array $statuses): array
    {
        $aliases = [];

        foreach ($statuses as $status) {
            $aliases[] = $status;

            if ($status === $this->completeInvoiceStatus()) {
                $aliases[] = 'invoiced';
                $aliases[] = 'invoice_completed';
            }

            if ($status === Order::STATUS_CANCELLED) {
                $aliases[] = 'canceled';
            }
        }

        return array_values(array_unique($aliases));
    }

    private function countDistinctOrders(Builder $query): int
    {
        return (int) $query
            ->select('orders.id')
            ->distinct()
            ->count('orders.id');
    }
}