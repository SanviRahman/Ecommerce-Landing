<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\TodayReportSummaryService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse | View
    {
        $user = auth()->user();

        if ($request->ajax()) {
            try {
                $filters   = $this->filters($request);
                $dateRange = $this->dateRangeFromFilter($filters);

                $orderQuery = $this->filteredOrderQuery($filters, $dateRange, true);

                $stats           = $this->dashboardStats(clone $orderQuery, $filters, $dateRange);
                $todayReport     = $this->dailyReportStats($filters, $dateRange);
                $productSaleRows = $this->productSaleRows($filters, $dateRange);
                $userReportRows  = $this->userOrderReportRows($request, $dateRange, $filters);

                return response()->json([
                    'status'      => true,
                    'stats'       => $stats,
                    'todayReport' => $todayReport,
                    'sections'    => [
                        'productSaleReport' => view('admin.dashboard.partials.product_sale_report_table', [
                            'productSaleRows' => $productSaleRows,
                        ])->render(),
                        'userOrderReport'   => view('admin.dashboard.partials.user_order_report_table', [
                            'userReportRows' => $userReportRows,
                        ])->render(),
                    ],
                ]);
            } catch (\Throwable $exception) {
                Log::error('Dashboard AJAX load failed', [
                    'message' => $exception->getMessage(),
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                ]);

                return response()->json([
                    'status'      => false,
                    'message'     => app()->environment('local')
                        ? $exception->getMessage()
                        : 'Dashboard data could not be loaded.',
                    'stats'       => $this->emptyStats(),
                    'todayReport' => $this->emptyTodayReport(),
                    'sections'    => [
                        'productSaleReport' => '<div class="text-center text-danger p-4"><i class="fas fa-exclamation-triangle"></i> Product sale report could not be loaded.</div>',
                        'userOrderReport'   => '<div class="text-center text-danger p-4"><i class="fas fa-exclamation-triangle"></i> User report could not be loaded.</div>',
                    ],
                ], 200);
            }
        }

        $campaigns = Campaign::query()
            ->when(Schema::hasColumn('campaigns', 'status'), fn($query) => $query->where('status', true))
            ->orderBy('title')
            ->get(['id', 'title']);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.dashboard.index', [
            'title'      => 'Dashboard',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ],
            'campaigns'  => $campaigns,
            'users'      => $users,
            'isEmployee' => $this->isEmployee($user),
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'date_filter'    => $request->input('date_filter', 'all'),
            'start_date'     => $request->input('start_date'),
            'end_date'       => $request->input('end_date'),
            'order_status'   => $request->input('order_status'),
            'payment_status' => $request->input('payment_status'),
            'delivery_area'  => $request->input('delivery_area'),
            'campaign_id'    => $request->input('campaign_id'),
        ];
    }

    /**
     * Resolve the business/report timezone from the same source used by
     * TodayReportSummaryService. This prevents cPanel/server timezone from
     * changing the Bangladesh reporting day.
     */
    private function displayTimezone(): string
    {
        return method_exists(Order::class, 'displayTimezone')
            ? Order::displayTimezone()
            : config(
                'app.order_display_timezone',
                'Asia/Dhaka'
            );
    }

    /**
     * Convert a Bangladesh local calendar window to the UTC window stored in
     * the database.
     */
    private function localWindowToDatabase(
        CarbonImmutable $localStart,
        CarbonImmutable $localEnd
    ): array {
        return [
            $localStart->utc(),
            $localEnd->utc(),
        ];
    }

    /**
     * Current Bangladesh day:
     * 12:00:00 AM through 11:59:59.999999 PM, converted to UTC.
     */
    private function databaseTodayRange(): array
    {
        $now = CarbonImmutable::now($this->displayTimezone());

        return $this->localWindowToDatabase(
            $now->startOfDay(),
            $now->endOfDay()
        );
    }

    private function dateRangeFromFilter(array $filters): array
    {
        $dateFilter = $filters['date_filter'] ?? 'all';
        $now = CarbonImmutable::now($this->displayTimezone());

        return match ($dateFilter) {
            'today' => $this->localWindowToDatabase(
                $now->startOfDay(),
                $now->endOfDay()
            ),

            'yesterday' => $this->localWindowToDatabase(
                $now->subDay()->startOfDay(),
                $now->subDay()->endOfDay()
            ),

            'this_week' => $this->localWindowToDatabase(
                $now->startOfWeek()->startOfDay(),
                $now->endOfWeek()->endOfDay()
            ),

            'this_month' => $this->localWindowToDatabase(
                $now->startOfMonth()->startOfDay(),
                $now->endOfMonth()->endOfDay()
            ),

            'last_month' => $this->localWindowToDatabase(
                $now->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                $now->subMonthNoOverflow()->endOfMonth()->endOfDay()
            ),

            'this_year' => $this->localWindowToDatabase(
                $now->startOfYear()->startOfDay(),
                $now->endOfYear()->endOfDay()
            ),

            'custom' => $this->customDateRange($filters),

            /*
             * "All Time" stays unbounded for the upper Total Count cards.
             * Daily Product/User reports separately map All Time to today.
             */
            default => [null, null],
        };
    }

    /**
     * Product Sale Report and User Order Report:
     * - All Time / Today = current Bangladesh calendar day.
     * - Other filters = selected Bangladesh local range converted to UTC.
     */
    private function reportActivityRange(
        array $filters,
        array $dateRange
    ): array {
        $dateFilter = $filters['date_filter'] ?? 'all';

        if (in_array($dateFilter, ['all', 'today'], true)) {
            return $this->databaseTodayRange();
        }

        [$start, $end] = $dateRange;

        /*
         * Invalid/empty custom date input must never turn the daily report into
         * an accidental all-time report.
         */
        if (! $start && ! $end) {
            return $this->databaseTodayRange();
        }

        return $dateRange;
    }

    private function customDateRange(array $filters): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $timezone = $this->displayTimezone();

        if (! $startDate && ! $endDate) {
            return [null, null];
        }

        try {
            $start = $startDate
                ? CarbonImmutable::parse(
                    $startDate,
                    $timezone
                )->startOfDay()
                : null;

            $end = $endDate
                ? CarbonImmutable::parse(
                    $endDate,
                    $timezone
                )->endOfDay()
                : null;
        } catch (\Throwable) {
            return [null, null];
        }

        if ($start && $end && $start->greaterThan($end)) {
            [$start, $end] = [
                $end->startOfDay(),
                $start->endOfDay(),
            ];
        }

        return [
            $start?->utc(),
            $end?->utc(),
        ];
    }

    private function filteredOrderQuery(array $filters, array $dateRange = [null, null], bool $applyDate = true): Builder
    {
        $user = auth()->user();

        $query = Order::query();

        if ($this->isEmployee($user) && Schema::hasColumn('orders', 'assigned_employee_id')) {
            $query->where('assigned_employee_id', $user->id);
        }

        if (! empty($filters['order_status'])) {
            $query->where('order_status', $filters['order_status']);

            if (Schema::hasColumn('orders', 'custom_order_list')) {
                $query->whereNull('custom_order_list');
            }
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['delivery_area'])) {
            $query->where('delivery_area', $filters['delivery_area']);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if ($applyDate) {
            $this->applyDateRange($query, $dateRange, 'created_at');
        }

        return $query;
    }

    private function applyDateRange(Builder $query, array $dateRange, string $column = 'created_at'): void
    {
        [$start, $end] = $dateRange;

        if ($start && $end) {
            $query->whereBetween($column, [$start, $end]);
            return;
        }

        if ($start) {
            $query->where($column, '>=', $start);
        }

        if ($end) {
            $query->where($column, '<=', $end);
        }
    }

    private function applyDateRangeToQuery($query, array $dateRange, string $column = 'created_at'): void
    {
        [$start, $end] = $dateRange;

        if ($start && $end) {
            $query->whereBetween($column, [$start, $end]);
            return;
        }

        if ($start) {
            $query->where($column, '>=', $start);
        }

        if ($end) {
            $query->where($column, '<=', $end);
        }
    }
    private function dashboardStats(Builder $orderQuery, array $filters, array $dateRange): array
    {
        $totalOrders = (clone $orderQuery)->count();

        $workflowOrderQuery = clone $orderQuery;

        if (Schema::hasColumn('orders', 'custom_order_list')) {
            $workflowOrderQuery->whereNull('custom_order_list');
        }

        $pendingOrders    = (clone $workflowOrderQuery)->where('order_status', 'pending')->count();
        $confirmedOrders  = (clone $workflowOrderQuery)->whereIn('order_status', ['confirmed', 'complete', 'completed'])->count();
        $processingOrders = (clone $workflowOrderQuery)->where('order_status', 'processing')->count();
        $deliveredOrders  = (clone $workflowOrderQuery)->where('order_status', 'delivered')->count();
        $cancelledOrders  = (clone $workflowOrderQuery)->whereIn('order_status', ['cancelled', 'canceled'])->count();
        $grossSales       = (clone $orderQuery)->sum('total_amount');

        $productQuery  = Product::query();
        $totalProducts = $productQuery->count();

        return [
            'totalOrders'      => number_format($totalOrders),
            'pendingOrders'    => number_format($pendingOrders),
            'confirmedOrders'  => number_format($confirmedOrders),
            'processingOrders' => number_format($processingOrders),
            'deliveredOrders'  => number_format($deliveredOrders),
            'cancelledOrders'  => number_format($cancelledOrders),
            'grossSales'       => $this->money($grossSales),
            'totalProducts'    => number_format($totalProducts),
        ];
    }
    private function dailyReportStats(array $filters, array $dateRange): array
    {
        /*
     * Today's Report is always fixed to the current Bangladesh calendar day.
     * Dashboard analytics date/campaign/status/payment/area filters do not
     * change this section.
     */
        $summary = app(TodayReportSummaryService::class)->summary(
            [],
            auth()->user()
        );

        return [
            'todaysOrder'        => number_format($summary['todays_order'] ?? 0),
            'newOrder'           => number_format($summary['new_order'] ?? 0),
            'incompletedOrder'   => number_format($summary['incompleted_order'] ?? 0),
            'completedOrder'     => number_format($summary['completed_order'] ?? 0),
            'completedInvoice'   => number_format($summary['completed_invoice'] ?? 0),
            'shippedOrders'      => number_format($summary['shipped_orders'] ?? 0),
            'deliveredOrder'     => number_format($summary['delivered_order'] ?? 0),
            'cancelled'          => number_format($summary['cancelled'] ?? 0),
            'pendingOrder'       => number_format($summary['pending_order'] ?? 0),
            'stockOutOrder'      => number_format($summary['stock_out_order'] ?? 0),
            'orderList1'         => number_format($summary['order_list_1'] ?? 0),
            'orderList2'         => number_format($summary['order_list_2'] ?? 0),
            'incompletedInvoice' => number_format($summary['incompleted_invoice'] ?? 0),
            'totalCheckout'      => number_format($summary['checkout'] ?? 0),
            'delivery'           => number_format($summary['delivery'] ?? 0),
        ];
    }

    /**
     * Count Order List 1/2 movement inside current report range.
     *
     * custom_order_list_moved_at is the source of truth.
     * updated_at fallback keeps already moved rows visible if timestamp column
     * was added after the move happened.
     */
    private function orderListActivityQuery(Builder $query, string $listName, array $dateRange): Builder
    {
        if (! Schema::hasColumn('orders', 'custom_order_list')) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('custom_order_list', $listName)
            ->where(function (Builder $movementQuery) use ($dateRange) {
                $this->applyOrderListMovementWindow($movementQuery, $dateRange);
            });

        return $query;
    }

    private function applyOrderListMovementWindow(Builder $query, array $dateRange): void
    {
        if (Schema::hasColumn('orders', 'custom_order_list_moved_at')) {
            $query->where(function (Builder $movementQuery) use ($dateRange) {
                $movementQuery->where(function (Builder $movedAtQuery) use ($dateRange) {
                    $this->applyDateRange($movedAtQuery, $dateRange, 'custom_order_list_moved_at');
                });

                if (Schema::hasColumn('orders', 'updated_at')) {
                    $movementQuery->orWhere(function (Builder $fallbackQuery) use ($dateRange) {
                        $fallbackQuery->whereNull('custom_order_list_moved_at')
                            ->where(function (Builder $updatedAtQuery) use ($dateRange) {
                                $this->applyDateRange($updatedAtQuery, $dateRange, 'updated_at');
                            });
                    });
                }
            });

            return;
        }

        $this->applyDateRange($query, $dateRange, 'updated_at');
    }

    private function pendingInvoiceCount(Builder $query): int
    {
        if (Schema::hasColumn('orders', 'invoice_printed_at')) {
            return $query->whereNull('invoice_printed_at')->count();
        }

        return $query->where(function (Builder $q) {
            $q->whereNull('payment_status')
                ->orWhereNotIn('payment_status', ['paid', 'collected']);
        })->count();
    }

    private function statusActionQuery(Builder $query, array $statuses, array $dateRange, array $fallbackColumns = ['updated_at']): Builder
    {
        $statuses = array_values(array_unique(array_filter($statuses)));

        /*
         * Current workflow statuses and Static Order List 1/2 are exclusive.
         */
        if (Schema::hasColumn('orders', 'custom_order_list')) {
            $query->whereNull('custom_order_list');
        }

        $query->whereIn('order_status', $statuses);

        $this->applyActionDateCondition(
            $query,
            $dateRange,
            $statuses,
            $fallbackColumns
        );

        return $query;
    }

    private function invoicedActionQuery(Builder $query, array $dateRange): Builder
    {
        /*
         * Complete Invoice is a current workflow status. An old
         * invoice_printed_at value must not count Shipped, Delivered,
         * Cancelled or custom-list orders as currently invoiced.
         */
        if (Schema::hasColumn('orders', 'custom_order_list')) {
            $query->whereNull('custom_order_list');
        }

        $query->where(
            'order_status',
            Order::STATUS_COMPLETE_INVOICE
        );

        $this->applyActionDateCondition(
            $query,
            $dateRange,
            [Order::STATUS_COMPLETE_INVOICE],
            ['invoice_printed_at', 'updated_at', 'created_at']
        );

        return $query;
    }

    private function pendingInvoiceActionQuery(Builder $query, array $dateRange): Builder
    {
        if (Schema::hasColumn('orders', 'custom_order_list')) {
            $query->whereNull('custom_order_list');
        }

        /*
         * Pending Invoice belongs only to the current Confirmed state.
         */
        $query->where('order_status', Order::STATUS_CONFIRMED);

        if (Schema::hasColumn('orders', 'invoice_printed_at')) {
            $query->whereNull('invoice_printed_at');
        } else {
            $query->where(function (Builder $q) {
                $q
                    ->whereNull('payment_status')
                    ->orWhereNotIn(
                        'payment_status',
                        ['paid', 'collected']
                    );
            });
        }

        $this->applyActionDateCondition(
            $query,
            $dateRange,
            [Order::STATUS_CONFIRMED],
            ['created_at', 'updated_at']
        );

        return $query;
    }

    private function applyActionDateCondition(Builder $query, array $dateRange, array $statuses = [], array $fallbackColumns = ['updated_at']): void
    {
        [$start, $end] = $dateRange;

        if (! $start && ! $end) {
            return;
        }

        $hasCondition = false;

        $query->where(function (Builder $dateQuery) use ($dateRange, $statuses, $fallbackColumns, &$hasCondition) {
            foreach ($fallbackColumns as $column) {
                if (! Schema::hasColumn('orders', $column)) {
                    continue;
                }

                $hasCondition = true;

                $dateQuery->orWhere(function (Builder $columnQuery) use ($dateRange, $column) {
                    $this->applyDateRange($columnQuery, $dateRange, $column);
                });
            }

            if ($statuses && Schema::hasTable('order_status_logs')) {
                $hasCondition = true;

                $dateQuery->orWhereExists(function ($subQuery) use ($dateRange, $statuses) {
                    $subQuery->select(DB::raw(1))
                        ->from('order_status_logs')
                        ->whereColumn('order_status_logs.order_id', 'orders.id')
                        ->whereIn('order_status_logs.status', $statuses);

                    if (Schema::hasColumn('order_status_logs', 'deleted_at')) {
                        $subQuery->whereNull('order_status_logs.deleted_at');
                    }

                    $this->applyDateRangeToQuery($subQuery, $dateRange, 'order_status_logs.created_at');
                });
            }
        });

        if (! $hasCondition) {
            $query->whereRaw('1 = 0');
        }
    }
    private function productSaleRows(array $filters, array $dateRange): array
    {
        /*
         * Product Sale Report follows the Dashboard daily report window.
         * - All Time / Today: current day, 12:00 AM to 11:59:59 PM.
         * - Other date filters: selected calendar range.
         */
        $reportRange = $this->reportActivityRange($filters, $dateRange);

        if (! Schema::hasTable('order_items')) {
            return [];
        }

        $user = auth()->user();

        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id');

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        if ($this->isEmployee($user) && Schema::hasColumn('orders', 'assigned_employee_id')) {
            $query->where('orders.assigned_employee_id', $user->id);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('orders.campaign_id', $filters['campaign_id']);
        }

        if (! empty($filters['order_status'])) {
            $query->where(
                'orders.order_status',
                $filters['order_status']
            );

            if (Schema::hasColumn('orders', 'custom_order_list')) {
                $query->whereNull('orders.custom_order_list');
            }
        }

        if (! empty($filters['payment_status'])) {
            $query->where('orders.payment_status', $filters['payment_status']);
        }

        if (! empty($filters['delivery_area'])) {
            $query->where('orders.delivery_area', $filters['delivery_area']);
        }

        $this->applyDateRangeToQuery($query, $reportRange, 'orders.created_at');

        $workflowOnlySql = Schema::hasColumn('orders', 'custom_order_list')
            ? " AND orders.custom_order_list IS NULL"
            : "";

        $customListOneSql = Schema::hasColumn('orders', 'custom_order_list')
            ? "COUNT(DISTINCT CASE WHEN orders.custom_order_list = 'order_list_1' THEN orders.id END)"
            : "0";

        $customListTwoSql = Schema::hasColumn('orders', 'custom_order_list')
            ? "COUNT(DISTINCT CASE WHEN orders.custom_order_list = 'order_list_2' THEN orders.id END)"
            : "0";

        $fakeSql = Schema::hasColumn('orders', 'is_fake')
            ? "COUNT(DISTINCT CASE WHEN (orders.is_fake = 1 OR orders.order_status = 'fake'){$workflowOnlySql} THEN orders.id END)"
            : "COUNT(DISTINCT CASE WHEN orders.order_status = 'fake'{$workflowOnlySql} THEN orders.id END)";

        $pendingInvoiceSql = Schema::hasColumn('orders', 'invoice_printed_at')
            ? "COUNT(DISTINCT CASE WHEN orders.order_status = 'confirmed' AND orders.invoice_printed_at IS NULL{$workflowOnlySql} THEN orders.id END)"
            : "0";

        /*
         * Product summary follows mutually exclusive CURRENT workflow states.
         * confirmed_at and invoice_printed_at remain audit fields only.
         */
        $completeOrderSql =
            "COUNT(DISTINCT CASE WHEN orders.order_status = 'confirmed'{$workflowOnlySql} THEN orders.id END)";

        $completeInvoiceSql =
            "COUNT(DISTINCT CASE WHEN orders.order_status = 'complete_invoice'{$workflowOnlySql} THEN orders.id END)";

        return $query
            ->selectRaw("
                COALESCE(order_items.product_id, products.id, 0) as product_id,
                NULLIF(TRIM(products.product_code), '') as product_code,
                COALESCE(order_items.product_name, products.name, 'Unknown Product') as product_name,

                COUNT(DISTINCT orders.id) as total_orders,

                COUNT(DISTINCT CASE WHEN orders.order_status = 'processing'{$workflowOnlySql} THEN orders.id END) as new_orders,
                COUNT(DISTINCT CASE WHEN orders.order_status = 'pending'{$workflowOnlySql} THEN orders.id END) as pending_orders,
                {$completeOrderSql} as complete_orders,
                COUNT(DISTINCT CASE WHEN orders.order_status IN ('cancelled', 'canceled'){$workflowOnlySql} THEN orders.id END) as cancelled_orders,

                {$customListOneSql} as order_list_1,
                {$customListTwoSql} as order_list_2,

                COUNT(DISTINCT CASE WHEN orders.order_status = 'shipped'{$workflowOnlySql} THEN orders.id END) as shipped_orders,
                COUNT(DISTINCT CASE WHEN orders.order_status = 'delivered'{$workflowOnlySql} THEN orders.id END) as delivered_orders,
                COUNT(DISTINCT CASE WHEN orders.order_status = 'stock_out'{$workflowOnlySql} THEN orders.id END) as stock_out_orders,
                {$fakeSql} as fake_orders,

                {$pendingInvoiceSql} as pending_invoice,
                {$completeInvoiceSql} as complete_invoice
            ")
            ->groupBy(
                'order_items.product_id',
                'order_items.product_name',
                'products.id',
                'products.product_code',
                'products.name'
            )
            ->orderByDesc('total_orders')
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();
    }

    private function userOrderReportRows(Request $request, array $dateRange, array $filters = []): array
    {
        $user           = auth()->user();
        $selectedUserId = $request->input('report_user_id');
        $reportRange    = $this->reportActivityRange($filters, $dateRange);

        $usersQuery = User::query()->orderBy('name');

        if ($this->isEmployee($user)) {
            $usersQuery->whereKey($user->id);
        } elseif ($selectedUserId) {
            $usersQuery->whereKey($selectedUserId);
        }

        $users = $usersQuery->get(['id', 'name']);

        return $users->map(function (User $reportUser) use ($reportRange) {
            $baseQuery = Order::query();

            if (Schema::hasColumn('orders', 'assigned_employee_id')) {
                $baseQuery->where('assigned_employee_id', $reportUser->id);
            } elseif (Schema::hasColumn('orders', 'user_id')) {
                $baseQuery->where('user_id', $reportUser->id);
            } else {
                $baseQuery->whereRaw('1 = 0');
            }

            $createdQuery = clone $baseQuery;
            $this->applyDateRange($createdQuery, $reportRange, 'created_at');

            $processingQuery = $this->statusActionQuery(
                clone $baseQuery,
                [Order::STATUS_PROCESSING],
                $reportRange,
                ['created_at', 'updated_at']
            );

            $cancelledQuery = $this->statusActionQuery(
                clone $baseQuery,
                [Order::STATUS_CANCELLED],
                $reportRange,
                ['cancelled_at', 'updated_at']
            );

            $completedQuery = $this->statusActionQuery(
                clone $baseQuery,
                [Order::STATUS_CONFIRMED],
                $reportRange,
                ['confirmed_at', 'updated_at']
            );

            $deliveredQuery = $this->statusActionQuery(
                clone $baseQuery,
                [Order::STATUS_DELIVERED],
                $reportRange,
                ['delivered_at', 'updated_at']
            );

            $stockOutQuery = $this->statusActionQuery(
                clone $baseQuery,
                [Order::STATUS_STOCK_OUT],
                $reportRange,
                ['updated_at']
            );
            $returnQuery     = $this->statusActionQuery(clone $baseQuery, ['return', 'returned'], $reportRange, ['updated_at']);
            $onHoldQuery     = $this->statusActionQuery(clone $baseQuery, ['on_hold', 'hold', 'customer_on_hold'], $reportRange, ['updated_at']);
            $invoicedQuery   = $this->invoicedActionQuery(clone $baseQuery, $reportRange);

            $paidQuery = clone $baseQuery;
            $paidQuery->whereIn('payment_status', ['paid', 'collected']);
            $this->applyActionDateCondition($paidQuery, $reportRange, ['paid', 'collected'], ['updated_at']);

            $pendingPaymentQuery = clone $baseQuery;
            $pendingPaymentQuery->whereIn('payment_status', ['cod_pending', 'pending', 'unpaid']);
            $this->applyActionDateCondition($pendingPaymentQuery, $reportRange, ['cod_pending', 'payment_pending', 'pending', 'unpaid'], ['created_at', 'updated_at']);

            return [
                'date'            => $this->dateRangeLabel($reportRange),
                'user_name'       => $reportUser->name,
                'total_order'     => (clone $createdQuery)->count(),
                'processing'      => $processingQuery->count(),
                'pending_payment' => $pendingPaymentQuery->count(),
                'on_hold'         => $onHoldQuery->count(),
                'cancelled'       => $cancelledQuery->count(),
                'completed'       => $completedQuery->count(),
                'invoiced'        => $invoicedQuery->count(),
                'stock_out'       => $stockOutQuery->count(),
                'delivered'       => $deliveredQuery->count(),
                'paid'            => $paidQuery->count(),
                'return'          => $returnQuery->count(),
                'paid_amount'     => $paidQuery->sum('total_amount'),
            ];
        })->toArray();
    }

    private function dateRangeLabel(array $dateRange): string
    {
        [$start, $end] = $dateRange;
        $timezone = $this->displayTimezone();

        $localStart = $start
            ? CarbonImmutable::instance($start)
                ->setTimezone($timezone)
            : null;

        $localEnd = $end
            ? CarbonImmutable::instance($end)
                ->setTimezone($timezone)
            : null;

        if ($localStart && $localEnd) {
            return $localStart->format('Y-m-d')
                . ' to '
                . $localEnd->format('Y-m-d');
        }

        if ($localStart) {
            return 'From ' . $localStart->format('Y-m-d');
        }

        if ($localEnd) {
            return 'Until ' . $localEnd->format('Y-m-d');
        }

        return 'All Time';
    }

    private function emptyStats(): array
    {
        return [
            'totalOrders'      => '0',
            'pendingOrders'    => '0',
            'confirmedOrders'  => '0',
            'processingOrders' => '0',
            'deliveredOrders'  => '0',
            'cancelledOrders'  => '0',
            'grossSales'       => '৳0',
            'totalProducts'    => '0',
        ];
    }

    private function emptyTodayReport(): array
    {
        return [
            'todaysOrder'        => '0',
            'newOrder'           => '0',
            'incompletedOrder'   => '0',
            'completedOrder'     => '0',
            'completedInvoice'   => '0',
            'shippedOrders'      => '0',
            'deliveredOrder'     => '0',
            'cancelled'          => '0',
            'pendingOrder'       => '0',
            'stockOutOrder'      => '0',
            'orderList1'         => '0',
            'orderList2'         => '0',
            'incompletedInvoice' => '0',
            'totalCheckout'      => '0',
            'delivery'           => '0',
        ];
    }

    private function money(int | float | string | null $amount): string
    {
        return '৳' . number_format((float) ($amount ?? 0));
    }

    private function isEmployee($user): bool
    {
        return $user && method_exists($user, 'isEmployee') && $user->isEmployee();
    }
}
