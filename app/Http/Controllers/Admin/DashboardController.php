<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $user = auth()->user();

        if ($request->ajax()) {
            $filters = $this->filters($request);

            $orderQuery = Order::query()
                ->with(['campaign', 'assignedEmployee']);

            $productQuery = Product::query();

            $campaignQuery = Campaign::query();

            /*
            |--------------------------------------------------------------------------
            | Employee Access
            |--------------------------------------------------------------------------
            */
            if ($user->isEmployee() && Schema::hasColumn('orders', 'assigned_employee_id')) {
                $orderQuery->where('assigned_employee_id', $user->id);
            }

            /*
            |--------------------------------------------------------------------------
            | Filters
            |--------------------------------------------------------------------------
            */
            if (! empty($filters['order_status'])) {
                $orderQuery->where('order_status', $filters['order_status']);
            }

            if (! empty($filters['payment_status'])) {
                $orderQuery->where('payment_status', $filters['payment_status']);
            }

            if (! empty($filters['delivery_area'])) {
                $orderQuery->where('delivery_area', $filters['delivery_area']);
            }

            if (! empty($filters['campaign_id'])) {
                $orderQuery->where('campaign_id', $filters['campaign_id']);
            }

            $this->applyDateFilter($orderQuery, $filters, 'created_at');

            /*
            |--------------------------------------------------------------------------
            | Stats
            |--------------------------------------------------------------------------
            */
            $totalOrders = (clone $orderQuery)->count();

            $pendingOrders = (clone $orderQuery)->where('order_status', 'pending')->count();
            $confirmedOrders = (clone $orderQuery)->where('order_status', 'confirmed')->count();
            $processingOrders = (clone $orderQuery)->where('order_status', 'processing')->count();
            $shippedOrders = (clone $orderQuery)->where('order_status', 'shipped')->count();
            $deliveredOrders = (clone $orderQuery)->where('order_status', 'delivered')->count();
            $cancelledOrders = (clone $orderQuery)->where('order_status', 'cancelled')->count();
            $fakeOrders = (clone $orderQuery)->where('is_fake', true)->count();

            $grossSales = (clone $orderQuery)->sum('total_amount');
            $deliveredSales = (clone $orderQuery)->where('order_status', 'delivered')->sum('total_amount');
            $shippingTotal = (clone $orderQuery)->sum('shipping_charge');
            $codTotal = (clone $orderQuery)->sum('cod_charge');

            $totalProducts = (clone $productQuery)->count();
            $activeProducts = (clone $productQuery)->where('status', true)->count();
            $totalCampaigns = (clone $campaignQuery)->count();
            $activeCampaigns = (clone $campaignQuery)->where('status', true)->count();

            /*
            |--------------------------------------------------------------------------
            | Charts
            |--------------------------------------------------------------------------
            */
            $orderTrend = $this->monthlySeries(clone $orderQuery, 'created_at', 'count');
            $salesTrend = $this->monthlySeries(clone $orderQuery, 'created_at', 'sum', 'total_amount');

            $statusComparison = $this->categorySeries([
                ['label' => 'Pending', 'value' => $pendingOrders],
                ['label' => 'Confirmed', 'value' => $confirmedOrders],
                ['label' => 'Processing', 'value' => $processingOrders],
                ['label' => 'Shipped', 'value' => $shippedOrders],
                ['label' => 'Delivered', 'value' => $deliveredOrders],
                ['label' => 'Cancelled', 'value' => $cancelledOrders],
            ]);

            $paymentComparison = $this->paymentStatusSeries(clone $orderQuery);
            $deliveryAreaComparison = $this->deliveryAreaSeries(clone $orderQuery);
            $campaignPerformance = $this->campaignPerformanceSeries(clone $orderQuery);

            /*
            |--------------------------------------------------------------------------
            | Recent Data
            |--------------------------------------------------------------------------
            */
            $recentOrders = (clone $orderQuery)
                ->latest()
                ->limit(8)
                ->get();

            $recentProducts = Product::query()
                ->latest()
                ->limit(8)
                ->get();

            $recentCampaigns = Campaign::query()
                ->latest()
                ->limit(8)
                ->get();

            return response()->json([
                'stats' => [
                    'totalOrders' => number_format($totalOrders),
                    'pendingOrders' => number_format($pendingOrders),
                    'confirmedOrders' => number_format($confirmedOrders),
                    'processingOrders' => number_format($processingOrders),
                    'shippedOrders' => number_format($shippedOrders),
                    'deliveredOrders' => number_format($deliveredOrders),
                    'cancelledOrders' => number_format($cancelledOrders),
                    'fakeOrders' => number_format($fakeOrders),

                    'grossSales' => $this->money($grossSales),
                    'deliveredSales' => $this->money($deliveredSales),
                    'shippingTotal' => $this->money($shippingTotal),
                    'codTotal' => $this->money($codTotal),

                    'totalProducts' => number_format($totalProducts),
                    'activeProducts' => number_format($activeProducts),
                    'totalCampaigns' => number_format($totalCampaigns),
                    'activeCampaigns' => number_format($activeCampaigns),
                ],

                'charts' => [
                    'orderTrend' => $orderTrend,
                    'salesTrend' => $salesTrend,
                    'statusComparison' => $statusComparison,
                    'paymentComparison' => $paymentComparison,
                    'deliveryAreaComparison' => $deliveryAreaComparison,
                    'campaignPerformance' => $campaignPerformance,
                ],

                'sections' => [
                    'recentOrders' => view('admin.dashboard.partials.recent_orders_table', compact('recentOrders'))->render(),
                    'recentProducts' => view('admin.dashboard.partials.recent_products_table', compact('recentProducts'))->render(),
                    'recentCampaigns' => view('admin.dashboard.partials.recent_campaigns_table', compact('recentCampaigns'))->render(),
                ],
            ]);
        }

        $campaigns = Campaign::query()
            ->where('status', true)
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('admin.dashboard.index', [
            'title' => 'Dashboard',
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ],
            'campaigns' => $campaigns,
            'isEmployee' => $user->isEmployee(),
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

    private function applyDateFilter(Builder $query, array $filters, string $column = 'created_at'): void
    {
        $dateFilter = $filters['date_filter'] ?? 'all';

        if ($dateFilter === 'today') {
            $query->whereDate($column, today());
            return;
        }

        if ($dateFilter === 'yesterday') {
            $query->whereDate($column, today()->subDay());
            return;
        }

        if ($dateFilter === 'this_week') {
            $query->whereBetween($column, [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ]);
            return;
        }

        if ($dateFilter === 'this_month') {
            $query->whereBetween($column, [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
            return;
        }

        if ($dateFilter === 'last_month') {
            $query->whereBetween($column, [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ]);
            return;
        }

        if ($dateFilter === 'this_year') {
            $query->whereBetween($column, [
                now()->startOfYear(),
                now()->endOfYear(),
            ]);
            return;
        }

        if ($dateFilter === 'custom') {
            if (! empty($filters['start_date'])) {
                $query->whereDate($column, '>=', $filters['start_date']);
            }

            if (! empty($filters['end_date'])) {
                $query->whereDate($column, '<=', $filters['end_date']);
            }
        }
    }

    private function monthlySeries(Builder $query, string $column, string $type = 'count', ?string $sumColumn = null): array
    {
        $labels = [];
        $values = [];

        for ($month = 1; $month <= 12; $month++) {
            $date = Carbon::create(now()->year, $month, 1);

            $labels[] = $date->format('M');

            $monthQuery = clone $query;

            $monthQuery->whereYear($column, $date->year)
                ->whereMonth($column, $date->month);

            $values[] = $type === 'sum'
                ? (float) $monthQuery->sum($sumColumn)
                : (int) $monthQuery->count();
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    private function categorySeries(array $rows): array
    {
        return [
            'labels' => collect($rows)->pluck('label')->values()->toArray(),
            'values' => collect($rows)->pluck('value')->map(fn ($value) => (float) $value)->values()->toArray(),
        ];
    }

    private function paymentStatusSeries(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw("payment_status as label, COUNT(*) as value")
            ->groupBy('payment_status')
            ->orderByDesc('value')
            ->get()
            ->map(function ($row) {
                return [
                    'label' => ucwords(str_replace('_', ' ', $row->label ?: 'Unknown')),
                    'value' => (int) $row->value,
                ];
            })
            ->toArray();

        return $this->categorySeries($rows);
    }

    private function deliveryAreaSeries(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw("delivery_area as label, COUNT(*) as value")
            ->groupBy('delivery_area')
            ->orderByDesc('value')
            ->get()
            ->map(function ($row) {
                return [
                    'label' => ucwords(str_replace('_', ' ', $row->label ?: 'Unknown')),
                    'value' => (int) $row->value,
                ];
            })
            ->toArray();

        return $this->categorySeries($rows);
    }

    private function campaignPerformanceSeries(Builder $query): array
    {
        $rows = (clone $query)
            ->leftJoin('campaigns', 'orders.campaign_id', '=', 'campaigns.id')
            ->selectRaw("COALESCE(campaigns.title, 'No Campaign') as label, COUNT(orders.id) as value")
            ->groupBy('orders.campaign_id', 'campaigns.title')
            ->orderByDesc('value')
            ->limit(8)
            ->get()
            ->toArray();

        return $this->categorySeries($rows);
    }

    private function money(int|float $amount): string
    {
        return '৳' . number_format($amount);
    }
}