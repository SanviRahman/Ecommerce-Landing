<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $user = auth()->user();

        if ($request->ajax()) {
            try {
                $filters = $this->filters($request);
                $dateRange = $this->dateRangeFromFilter($filters);

                $orderQuery = $this->filteredOrderQuery($filters, $dateRange, true);

                $stats = $this->dashboardStats(clone $orderQuery, $filters, $dateRange);
                $todayReport = $this->dailyReportStats($filters, $dateRange);
                $productSaleRows = $this->productSaleRows($filters, $dateRange);
                $userReportRows = $this->userOrderReportRows($request, $dateRange, $filters);

                return response()->json([
                    'status' => true,
                    'stats' => $stats,
                    'todayReport' => $todayReport,
                    'sections' => [
                        'productSaleReport' => view('admin.dashboard.partials.product_sale_report_table', [
                            'productSaleRows' => $productSaleRows,
                        ])->render(),
                        'userOrderReport' => view('admin.dashboard.partials.user_order_report_table', [
                            'userReportRows' => $userReportRows,
                        ])->render(),
                    ],
                ]);
            } catch (\Throwable $exception) {
                Log::error('Dashboard AJAX load failed', [
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]);

                return response()->json([
                    'status' => false,
                    'message' => app()->environment('local')
                        ? $exception->getMessage()
                        : 'Dashboard data could not be loaded.',
                    'stats' => $this->emptyStats(),
                    'todayReport' => $this->emptyTodayReport(),
                    'sections' => [
                        'productSaleReport' => '<div class="text-center text-danger p-4"><i class="fas fa-exclamation-triangle"></i> Product sale report could not be loaded.</div>',
                        'userOrderReport' => '<div class="text-center text-danger p-4"><i class="fas fa-exclamation-triangle"></i> User report could not be loaded.</div>',
                    ],
                ], 200);
            }
        }

        $campaigns = Campaign::query()
            ->when(Schema::hasColumn('campaigns', 'status'), fn ($query) => $query->where('status', true))
            ->orderBy('title')
            ->get(['id', 'title']);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.dashboard.index', [
            'title' => 'Dashboard',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ],
            'campaigns' => $campaigns,
            'users' => $users,
            'isEmployee' => $this->isEmployee($user),
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'date_filter' => $request->input('date_filter', 'all'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'order_status' => $request->input('order_status'),
            'payment_status' => $request->input('payment_status'),
            'delivery_area' => $request->input('delivery_area'),
            'campaign_id' => $request->input('campaign_id'),
        ];
    }

    private function dateRangeFromFilter(array $filters): array
    {
        $dateFilter = $filters['date_filter'] ?? 'all';
        $now = now();

        return match ($dateFilter) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek()->startOfDay(), $now->copy()->endOfWeek()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfMonth()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(), $now->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay()],
            'this_year' => [$now->copy()->startOfYear()->startOfDay(), $now->copy()->endOfYear()->endOfDay()],
            'custom' => $this->customDateRange($filters),
            default => [null, null],
        };
    }

    /**
     * Dashboard Report/Todays Report rule:
     * - All Time/Today view uses the current calendar day.
     * - Range is exactly 12:00 AM to 11:59:59 PM in app timezone.
     * - Other date filters keep their selected calendar range.
     */
    private function reportActivityRange(array $filters, array $dateRange): array
    {
        $dateFilter = $filters['date_filter'] ?? 'all';

        if (in_array($dateFilter, ['all', 'today'], true)) {
            $now = now();

            return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        }

        return $dateRange;
    }

    private function customDateRange(array $filters): array
    {
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;

        try {
            $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
            $end = $endDate ? Carbon::parse($endDate)->endOfDay() : null;
        } catch (\Throwable $exception) {
            return [null, null];
        }

        return [$start, $end];
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
        $pendingOrders = (clone $orderQuery)->where('order_status', 'pending')->count();
        $confirmedOrders = (clone $orderQuery)->whereIn('order_status', ['confirmed', 'complete', 'completed'])->count();
        $processingOrders = (clone $orderQuery)->where('order_status', 'processing')->count();
        $deliveredOrders = (clone $orderQuery)->where('order_status', 'delivered')->count();
        $cancelledOrders = (clone $orderQuery)->whereIn('order_status', ['cancelled', 'canceled'])->count();
        $grossSales = (clone $orderQuery)->sum('total_amount');

        $productQuery = Product::query();
        $totalProducts = $productQuery->count();

        return [
            'totalOrders' => number_format($totalOrders),
            'pendingOrders' => number_format($pendingOrders),
            'confirmedOrders' => number_format($confirmedOrders),
            'processingOrders' => number_format($processingOrders),
            'deliveredOrders' => number_format($deliveredOrders),
            'cancelledOrders' => number_format($cancelledOrders),
            'grossSales' => $this->money($grossSales),
            'totalProducts' => number_format($totalProducts),
        ];
    }
private function dailyReportStats(array $filters, array $dateRange): array
    {
        $reportRange = $this->reportActivityRange($filters, $dateRange);

        /*
         * Daily / Today Report rule:
         * - Today window is 12:00 AM to 11:59:59 PM.
         * - Today's Order / Total Checkout = orders created inside report range.
         * - Order List 1/2 = orders moved into custom list inside report range.
         * - Complete, Shipped, Delivered, Cancelled, Invoice = action based count.
         */
        $createdOrderQuery = $this->filteredOrderQuery($filters, $reportRange, true);
        $actionOrderQuery = $this->filteredOrderQuery($filters, [null, null], false);

        $createdOrMovedOrderQuery = $this->createdOrMovedOrderQuery(
            clone $actionOrderQuery,
            $reportRange
        );

        $pendingOrderQuery = $this->statusActionQuery(
            clone $actionOrderQuery,
            ['pending'],
            $reportRange,
            ['created_at', 'updated_at']
        );

        $completedQuery = $this->statusActionQuery(
            clone $actionOrderQuery,
            ['confirmed', 'complete', 'completed'],
            $reportRange,
            ['confirmed_at', 'updated_at']
        );

        $shippedQuery = $this->statusActionQuery(
            clone $actionOrderQuery,
            ['shipped'],
            $reportRange,
            ['updated_at']
        );

        $orderListOneQuery = $this->orderListActivityQuery(
            clone $actionOrderQuery,
            'order_list_1',
            $reportRange
        );

        $orderListTwoQuery = $this->orderListActivityQuery(
            clone $actionOrderQuery,
            'order_list_2',
            $reportRange
        );

        $invoiceCompletedQuery = $this->invoicedActionQuery(clone $actionOrderQuery, $reportRange);
        $pendingInvoiceQuery = $this->pendingInvoiceActionQuery(clone $actionOrderQuery, $reportRange);

        $deliveryQuery = $this->statusActionQuery(
            clone $actionOrderQuery,
            ['delivered'],
            $reportRange,
            ['delivered_at', 'updated_at']
        );

        $cancelledQuery = $this->statusActionQuery(
            clone $actionOrderQuery,
            ['cancelled', 'canceled'],
            $reportRange,
            ['cancelled_at', 'updated_at']
        );

        return [
            'todaysOrder' => number_format(
                (clone $createdOrMovedOrderQuery)
                    ->select('orders.id')
                    ->distinct()
                    ->count('orders.id')
            ),
            'pendingOrder' => number_format($pendingOrderQuery->count()),
            'incompletedOrder' => number_format(
                (clone $createdOrderQuery)
                    ->whereNotIn('order_status', [
                        'confirmed',
                        'complete',
                        'completed',
                        'shipped',
                        'delivered',
                        'cancelled',
                        'canceled',
                        'fake',
                        'stock_out',
                    ])
                    ->count()
            ),
            'completedOrder' => number_format($completedQuery->count()),
            'shippedOrders' => number_format($shippedQuery->count()),
            'orderList1' => number_format($orderListOneQuery->count()),
            'orderList2' => number_format($orderListTwoQuery->count()),
            'incompletedInvoice' => number_format($pendingInvoiceQuery->count()),
            'completedInvoice' => number_format($invoiceCompletedQuery->count()),
            'totalCheckout' => number_format((clone $createdOrderQuery)->count()),
            'delivery' => number_format($deliveryQuery->count()),
            'cancelled' => number_format($cancelledQuery->count()),
        ];
    }

    /**
     * Today's Order should match Report Management behavior:
     * created today OR moved into Order List 1/2 today.
     */
    private function createdOrMovedOrderQuery(Builder $query, array $dateRange): Builder
    {
        $query->where(function (Builder $activityQuery) use ($dateRange) {
            $activityQuery->where(function (Builder $createdQuery) use ($dateRange) {
                $this->applyDateRange($createdQuery, $dateRange, 'created_at');
            });

            if (Schema::hasColumn('orders', 'custom_order_list')) {
                $activityQuery->orWhere(function (Builder $listQuery) use ($dateRange) {
                    $listQuery->whereIn('custom_order_list', ['order_list_1', 'order_list_2'])
                        ->where(function (Builder $movementQuery) use ($dateRange) {
                            $this->applyOrderListMovementWindow($movementQuery, $dateRange);
                        });
                });
            }
        });

        return $query;
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

        $query->whereIn('order_status', $statuses);
        $this->applyActionDateCondition($query, $dateRange, $statuses, $fallbackColumns);

        return $query;
    }

    private function invoicedActionQuery(Builder $query, array $dateRange): Builder
    {
        if (Schema::hasColumn('orders', 'invoice_printed_at')) {
            $query->whereNotNull('invoice_printed_at');
            $this->applyDateRange($query, $dateRange, 'invoice_printed_at');

            return $query;
        }

        $query->whereIn('payment_status', ['paid', 'collected']);
        $this->applyActionDateCondition($query, $dateRange, ['invoiced', 'invoice_completed', 'paid', 'collected'], ['updated_at']);

        return $query;
    }

    private function pendingInvoiceActionQuery(Builder $query, array $dateRange): Builder
    {
        if (Schema::hasColumn('orders', 'invoice_printed_at')) {
            $query->whereNull('invoice_printed_at');
        } else {
            $query->where(function (Builder $q) {
                $q->whereNull('payment_status')
                    ->orWhereNotIn('payment_status', ['paid', 'collected']);
            });
        }

        // Pending invoice has no dedicated timestamp in the current schema.
        // So we count orders that are still pending invoice and were created/updated inside the report window.
        // If order_status_logs contains pending_invoice/invoice_pending status, that will also be counted accurately.
        $this->applyActionDateCondition(
            $query,
            $dateRange,
            ['pending_invoice', 'invoice_pending', 'pending'],
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
            $query->where('orders.order_status', $filters['order_status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('orders.payment_status', $filters['payment_status']);
        }

        if (! empty($filters['delivery_area'])) {
            $query->where('orders.delivery_area', $filters['delivery_area']);
        }

        $this->applyDateRangeToQuery($query, $dateRange, 'orders.created_at');

        $customListOneSql = Schema::hasColumn('orders', 'custom_order_list')
            ? "COUNT(DISTINCT CASE WHEN orders.custom_order_list = 'order_list_1' THEN orders.id END)"
            : "0";

        $customListTwoSql = Schema::hasColumn('orders', 'custom_order_list')
            ? "COUNT(DISTINCT CASE WHEN orders.custom_order_list = 'order_list_2' THEN orders.id END)"
            : "0";

        $fakeSql = Schema::hasColumn('orders', 'is_fake')
            ? "COUNT(DISTINCT CASE WHEN orders.is_fake = 1 OR orders.order_status = 'fake' THEN orders.id END)"
            : "COUNT(DISTINCT CASE WHEN orders.order_status = 'fake' THEN orders.id END)";

        $pendingInvoiceSql = Schema::hasColumn('orders', 'invoice_printed_at')
            ? "COUNT(DISTINCT CASE WHEN orders.order_status = 'confirmed' AND orders.invoice_printed_at IS NULL THEN orders.id END)"
            : "0";

        $completeInvoiceSql = Schema::hasColumn('orders', 'invoice_printed_at')
            ? "COUNT(DISTINCT CASE WHEN orders.invoice_printed_at IS NOT NULL THEN orders.id END)"
            : "0";

        return $query
            ->selectRaw("
                COALESCE(order_items.product_id, products.id, 0) as product_id,
                COALESCE(order_items.product_name, products.name, 'Unknown Product') as product_name,

                COUNT(DISTINCT orders.id) as total_orders,

                COUNT(DISTINCT CASE WHEN orders.order_status = 'processing' THEN orders.id END) as new_orders,
                COUNT(DISTINCT CASE WHEN orders.order_status = 'pending' THEN orders.id END) as pending_orders,
                COUNT(DISTINCT CASE WHEN orders.order_status IN ('confirmed', 'complete', 'completed') THEN orders.id END) as complete_orders,
                COUNT(DISTINCT CASE WHEN orders.order_status IN ('cancelled', 'canceled') THEN orders.id END) as cancelled_orders,

                {$customListOneSql} as order_list_1,
                {$customListTwoSql} as order_list_2,

                COUNT(DISTINCT CASE WHEN orders.order_status = 'shipped' THEN orders.id END) as shipped_orders,
                COUNT(DISTINCT CASE WHEN orders.order_status = 'delivered' THEN orders.id END) as delivered_orders,
                COUNT(DISTINCT CASE WHEN orders.order_status = 'stock_out' THEN orders.id END) as stock_out_orders,
                {$fakeSql} as fake_orders,

                {$pendingInvoiceSql} as pending_invoice,
                {$completeInvoiceSql} as complete_invoice
            ")
            ->groupBy('order_items.product_id', 'order_items.product_name', 'products.id', 'products.name')
            ->orderByDesc('total_orders')
            ->limit(100)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }


    private function userOrderReportRows(Request $request, array $dateRange, array $filters = []): array
    {
        $user = auth()->user();
        $selectedUserId = $request->input('report_user_id');
        $reportRange = $this->reportActivityRange($filters, $dateRange);

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

            $processingQuery = $this->statusActionQuery(clone $baseQuery, ['processing'], $reportRange, ['created_at', 'updated_at']);
            $cancelledQuery = $this->statusActionQuery(clone $baseQuery, ['cancelled'], $reportRange, ['cancelled_at', 'updated_at']);
            $completedQuery = $this->statusActionQuery(clone $baseQuery, ['delivered', 'complete', 'completed'], $reportRange, ['delivered_at', 'updated_at']);
            $deliveredQuery = $this->statusActionQuery(clone $baseQuery, ['delivered', 'complete', 'completed'], $reportRange, ['delivered_at', 'updated_at']);
            $stockOutQuery = $this->statusActionQuery(clone $baseQuery, ['stock_out'], $reportRange, ['updated_at']);
            $returnQuery = $this->statusActionQuery(clone $baseQuery, ['return', 'returned'], $reportRange, ['updated_at']);
            $onHoldQuery = $this->statusActionQuery(clone $baseQuery, ['on_hold', 'hold', 'customer_on_hold'], $reportRange, ['updated_at']);
            $invoicedQuery = $this->invoicedActionQuery(clone $baseQuery, $reportRange);

            $paidQuery = clone $baseQuery;
            $paidQuery->whereIn('payment_status', ['paid', 'collected']);
            $this->applyActionDateCondition($paidQuery, $reportRange, ['paid', 'collected'], ['updated_at']);

            $pendingPaymentQuery = clone $baseQuery;
            $pendingPaymentQuery->whereIn('payment_status', ['cod_pending', 'pending', 'unpaid']);
            $this->applyActionDateCondition($pendingPaymentQuery, $reportRange, ['cod_pending', 'payment_pending', 'pending', 'unpaid'], ['created_at', 'updated_at']);

            return [
                'date' => $this->dateRangeLabel($reportRange),
                'user_name' => $reportUser->name,
                'total_order' => (clone $createdQuery)->count(),
                'processing' => $processingQuery->count(),
                'pending_payment' => $pendingPaymentQuery->count(),
                'on_hold' => $onHoldQuery->count(),
                'cancelled' => $cancelledQuery->count(),
                'completed' => $completedQuery->count(),
                'invoiced' => $invoicedQuery->count(),
                'stock_out' => $stockOutQuery->count(),
                'delivered' => $deliveredQuery->count(),
                'paid' => $paidQuery->count(),
                'return' => $returnQuery->count(),
                'paid_amount' => $paidQuery->sum('total_amount'),
            ];
        })->toArray();
    }


    private function dateRangeLabel(array $dateRange): string
    {
        [$start, $end] = $dateRange;

        if ($start && $end) {
            return $start->format('Y-m-d') . ' to ' . $end->format('Y-m-d');
        }

        if ($start) {
            return 'From ' . $start->format('Y-m-d');
        }

        if ($end) {
            return 'Until ' . $end->format('Y-m-d');
        }

        return 'All Time';
    }

    private function emptyStats(): array
    {
        return [
            'totalOrders' => '0',
            'pendingOrders' => '0',
            'confirmedOrders' => '0',
            'processingOrders' => '0',
            'deliveredOrders' => '0',
            'cancelledOrders' => '0',
            'grossSales' => '৳0',
            'totalProducts' => '0',
        ];
    }

    private function emptyTodayReport(): array
    {
        return [
            'todaysOrder' => '0',
            'pendingOrder' => '0',
            'incompletedOrder' => '0',
            'completedOrder' => '0',
            'shippedOrders' => '0',
            'orderList1' => '0',
            'orderList2' => '0',
            'incompletedInvoice' => '0',
            'completedInvoice' => '0',
            'totalCheckout' => '0',
            'delivery' => '0',
            'cancelled' => '0',
        ];
    }

    private function money(int|float|string|null $amount): string
    {
        return '৳' . number_format((float) ($amount ?? 0));
    }

    private function isEmployee($user): bool
    {
        return $user && method_exists($user, 'isEmployee') && $user->isEmployee();
    }
}