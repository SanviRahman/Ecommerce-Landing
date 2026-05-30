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
                $userReportRows = $this->userOrderReportRows($request, $dateRange);

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
        $confirmedOrders = (clone $orderQuery)->where('order_status', 'confirmed')->count();
        $processingOrders = (clone $orderQuery)->where('order_status', 'processing')->count();
        $deliveredOrders = (clone $orderQuery)->whereIn('order_status', ['delivered', 'complete', 'completed'])->count();
        $cancelledOrders = (clone $orderQuery)->where('order_status', 'cancelled')->count();
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
        $createdOrderQuery = $this->filteredOrderQuery($filters, $dateRange, true);
        $actionOrderQuery = $this->filteredOrderQuery($filters, [null, null], false);

        $completedQuery = clone $actionOrderQuery;
        $completedQuery->whereIn('order_status', ['delivered', 'complete', 'completed']);
        $this->applyDateRange($completedQuery, $dateRange, 'updated_at');

        $invoiceCompletedQuery = clone $actionOrderQuery;
        if (Schema::hasColumn('orders', 'invoice_printed_at')) {
            $invoiceCompletedQuery->whereNotNull('invoice_printed_at');
            $this->applyDateRange($invoiceCompletedQuery, $dateRange, 'invoice_printed_at');
        } else {
            $invoiceCompletedQuery->whereIn('payment_status', ['paid', 'collected']);
            $this->applyDateRange($invoiceCompletedQuery, $dateRange, 'updated_at');
        }

        $deliveryQuery = clone $actionOrderQuery;
        $deliveryQuery->whereIn('order_status', ['shipped', 'delivered', 'complete', 'completed']);
        $this->applyDateRange($deliveryQuery, $dateRange, 'updated_at');

        $cancelledQuery = clone $actionOrderQuery;
        $cancelledQuery->where('order_status', 'cancelled');
        $this->applyDateRange($cancelledQuery, $dateRange, 'updated_at');

        return [
            'todaysOrder' => number_format((clone $createdOrderQuery)->count()),
            'pendingOrder' => number_format((clone $createdOrderQuery)->where('order_status', 'pending')->count()),
            'incompletedOrder' => number_format((clone $createdOrderQuery)->whereNotIn('order_status', ['delivered', 'complete', 'completed', 'cancelled', 'fake'])->count()),
            'completedOrder' => number_format($completedQuery->count()),
            'incompletedInvoice' => number_format($this->pendingInvoiceCount(clone $createdOrderQuery)),
            'completedInvoice' => number_format($invoiceCompletedQuery->count()),
            'totalCheckout' => number_format((clone $createdOrderQuery)->count()),
            'delivery' => number_format($deliveryQuery->count()),
            'cancelled' => number_format($cancelledQuery->count()),
        ];
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

    private function productSaleRows(array $filters, array $dateRange): array
    {
        if (! Schema::hasTable('order_items')) {
            return [];
        }

        $user = auth()->user();
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id');

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

        $invoiceSql = Schema::hasColumn('orders', 'invoice_printed_at')
            ? "COUNT(DISTINCT CASE WHEN orders.invoice_printed_at IS NOT NULL THEN orders.id END)"
            : "COUNT(DISTINCT CASE WHEN orders.payment_status IN ('paid', 'collected') THEN orders.id END)";

        return $query
            ->selectRaw("\n                COALESCE(order_items.product_id, products.id, 0) as product_id,\n                COALESCE(order_items.product_name, products.name, 'Unknown Product') as product_name,\n                COUNT(DISTINCT orders.id) as total_orders,\n                {$invoiceSql} as invoiced,\n                COUNT(DISTINCT CASE WHEN orders.order_status IN ('delivered', 'complete', 'completed') THEN orders.id END) as delivered,\n                COUNT(DISTINCT CASE WHEN orders.order_status = 'cancelled' THEN orders.id END) as cancelled,\n                COUNT(DISTINCT CASE WHEN orders.order_status IN ('on_hold', 'hold', 'customer_on_hold') THEN orders.id END) as customer_on_hold,\n                COUNT(DISTINCT CASE WHEN orders.payment_status IN ('cod_pending', 'pending', 'unpaid') OR orders.payment_status IS NULL THEN orders.id END) as payment_pending,\n                COUNT(DISTINCT CASE WHEN orders.order_status = 'processing' THEN orders.id END) as processing\n            ")
            ->groupBy('order_items.product_id', 'order_items.product_name', 'products.id', 'products.name')
            ->orderByDesc('total_orders')
            ->limit(100)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    private function userOrderReportRows(Request $request, array $dateRange): array
    {
        $user = auth()->user();
        $selectedUserId = $request->input('report_user_id');

        $usersQuery = User::query()->orderBy('name');

        if ($this->isEmployee($user)) {
            $usersQuery->whereKey($user->id);
        } elseif ($selectedUserId) {
            $usersQuery->whereKey($selectedUserId);
        }

        $users = $usersQuery->get(['id', 'name']);

        return $users->map(function (User $reportUser) use ($dateRange) {
            $baseQuery = Order::query();

            if (Schema::hasColumn('orders', 'assigned_employee_id')) {
                $baseQuery->where('assigned_employee_id', $reportUser->id);
            } elseif (Schema::hasColumn('orders', 'user_id')) {
                $baseQuery->where('user_id', $reportUser->id);
            } else {
                $baseQuery->whereRaw('1 = 0');
            }

            $createdQuery = clone $baseQuery;
            $this->applyDateRange($createdQuery, $dateRange, 'created_at');

            $invoicedQuery = clone $baseQuery;
            if (Schema::hasColumn('orders', 'invoice_printed_at')) {
                $invoicedQuery->whereNotNull('invoice_printed_at');
                $this->applyDateRange($invoicedQuery, $dateRange, 'invoice_printed_at');
            } else {
                $invoicedQuery->whereIn('payment_status', ['paid', 'collected']);
                $this->applyDateRange($invoicedQuery, $dateRange, 'updated_at');
            }

            $paidQuery = clone $baseQuery;
            $paidQuery->whereIn('payment_status', ['paid', 'collected']);
            $this->applyDateRange($paidQuery, $dateRange, 'updated_at');

            return [
                'date' => $this->dateRangeLabel($dateRange),
                'user_name' => $reportUser->name,
                'total_order' => (clone $createdQuery)->count(),
                'processing' => (clone $createdQuery)->where('order_status', 'processing')->count(),
                'pending_payment' => (clone $createdQuery)->whereIn('payment_status', ['cod_pending', 'pending', 'unpaid'])->count(),
                'on_hold' => (clone $createdQuery)->whereIn('order_status', ['on_hold', 'hold', 'customer_on_hold'])->count(),
                'cancelled' => (clone $createdQuery)->where('order_status', 'cancelled')->count(),
                'completed' => (clone $createdQuery)->whereIn('order_status', ['delivered', 'complete', 'completed'])->count(),
                'invoiced' => $invoicedQuery->count(),
                'stock_out' => (clone $createdQuery)->where('order_status', 'stock_out')->count(),
                'delivered' => (clone $createdQuery)->whereIn('order_status', ['delivered', 'complete', 'completed'])->count(),
                'paid' => $paidQuery->count(),
                'return' => (clone $createdQuery)->whereIn('order_status', ['return', 'returned'])->count(),
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
