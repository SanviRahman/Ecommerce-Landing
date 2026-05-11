<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\TrackingPixel;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    private function adminOnly(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function reportTypes(): array
    {
        return [
            'order_report'          => 'Order Report',
            'sales_report'          => 'Sales Report',
            'campaign_report'       => 'Campaign Report',
            'product_report'        => 'Product Report',
            'employee_order_report' => 'Employee Order Report',
            'fake_order_report'     => 'Fake Order Report',
            'payment_report'        => 'Payment Report',
            'customer_report'       => 'Customer Report',
            'delivery_report'       => 'Delivery Report',
            'tracking_pixel_report' => 'Tracking Pixel Report',
        ];
    }

    private function formats(): array
    {
        return [
            'html'  => 'HTML Preview',
            'csv'   => 'CSV Export',
            'pdf'   => 'PDF Export',
            'excel' => 'Excel Export',
        ];
    }

    private function groupByOptions(): array
    {
        return [
            'daily'          => 'Daily',
            'weekly'         => 'Weekly',
            'monthly'        => 'Monthly',
            'campaign'       => 'Campaign',
            'product'        => 'Product',
            'employee'       => 'Employee',
            'status'         => 'Order Status',
            'payment_status' => 'Payment Status',
            'delivery_area'  => 'Delivery Area',
        ];
    }

    private function reportQuery(bool $trash = false): Builder
    {
        return $trash
            ? ReportExport::onlyTrashed()->with('generatedBy')->latest()
            : ReportExport::query()->with('generatedBy')->latest();
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('report_uid', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('report_type', 'like', "%{$search}%")
                    ->orWhere('format', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($request->filled('report_type') && $request->report_type !== 'all') {
            $query->where('report_type', $request->report_type);
        }

        if ($request->filled('format') && $request->format !== 'all') {
            $query->where('format', $request->format);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('generated_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('generated_at', '<=', $request->date_to);
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Today's Report Summary
    |--------------------------------------------------------------------------
    | Report index top cards-এর জন্য শুধু today's data calculate করবে।
    | View file-এ $summary অথবা $summaryStats দুইভাবেই data পাওয়া যাবে।
    */
    private function getReportSummaryStats(): array
    {
        $todayOrdersQuery = Order::query()
            ->whereDate('created_at', today());

        return [
            'todays_order' => (clone $todayOrdersQuery)->count(),

            'pending_order' => (clone $todayOrdersQuery)
                ->where('order_status', Order::STATUS_PENDING)
                ->count(),

            'incompleted_order' => (clone $todayOrdersQuery)
                ->whereNotIn('order_status', [
                    Order::STATUS_DELIVERED,
                    Order::STATUS_CANCELLED,
                    Order::STATUS_FAKE,
                ])
                ->count(),

            'completed_order' => (clone $todayOrdersQuery)
                ->where('order_status', Order::STATUS_DELIVERED)
                ->count(),

            'incompleted_invoice' => (clone $todayOrdersQuery)
                ->where(function ($query) {
                    $query->where('payment_status', '!=', Order::PAYMENT_STATUS_COLLECTED)
                        ->orWhereNull('payment_status');
                })
                ->count(),

            'completed_invoice' => (clone $todayOrdersQuery)
                ->where('payment_status', Order::PAYMENT_STATUS_COLLECTED)
                ->count(),

            'checkout' => (clone $todayOrdersQuery)->count(),

            'delivery' => (clone $todayOrdersQuery)
                ->whereIn('order_status', [
                    Order::STATUS_SHIPPED,
                    Order::STATUS_DELIVERED,
                ])
                ->count(),

            'cancelled' => (clone $todayOrdersQuery)
                ->where('order_status', Order::STATUS_CANCELLED)
                ->count(),
        ];
    }

    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $summaryStats = $this->getReportSummaryStats();

        $query = $this->applyFilters($query, $request);

        $reports = $query->paginate(10)->withQueryString();

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Reports', 'url' => route('admin.reports.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = [
                'text' => 'Trash',
                'url'  => route('admin.reports.trashed'),
            ];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html'   => view('admin.reports.partials.table', [
                    'reports'     => $reports,
                    'reportTypes' => $this->reportTypes(),
                    'isTrash'     => $isTrash,
                ])->render(),
            ]);
        }

        return view('admin.reports.index', [
            'reports'        => $reports,
            'title'          => $title,
            'breadcrumb'     => $breadcrumb,
            'isTrash'        => $isTrash,
            'reportTypes'    => $this->reportTypes(),
            'formats'        => $this->formats(),
            'groupByOptions' => $this->groupByOptions(),

            /*
            |--------------------------------------------------------------------------
            | Important Fix
            |--------------------------------------------------------------------------
            | তোমার index.blade.php আগে $summary read করছে,
            | আর controller আগে শুধু $summaryStats পাঠাচ্ছিল।
            | তাই এখন দুই নামেই data পাঠানো হলো।
            */
            'summary'        => $summaryStats,
            'summaryStats'   => $summaryStats,
        ]);
    }

    public function index(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse(
            $request,
            $this->reportQuery(),
            'Report Manage'
        );
    }

    public function create()
    {
        $this->adminOnly();

        return view('admin.reports.create', [
            'title'          => 'Generate Report',
            'reportTypes'    => $this->reportTypes(),
            'formats'        => $this->formats(),
            'groupByOptions' => $this->groupByOptions(),
            'campaigns'      => Campaign::query()->orderBy('title')->get(['id', 'title']),
            'products'       => Product::query()->orderBy('name')->get(['id', 'name']),
            'employees'      => User::query()->orderBy('name')->get(['id', 'name']),
            'breadcrumb'     => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Reports', 'url' => route('admin.reports.index')],
                ['text' => 'Generate Report', 'url' => route('admin.reports.create')],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        $validated = $request->validate([
            'title'       => ['nullable', 'string', 'max:255'],
            'report_type' => ['required', 'string', 'in:' . implode(',', array_keys($this->reportTypes()))],
            'date_from'   => ['nullable', 'date'],
            'date_to'     => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by'    => ['nullable', 'string', 'in:' . implode(',', array_keys($this->groupByOptions()))],
            'format'      => ['required', 'string', 'in:html,csv,pdf,excel'],
            'filters'     => ['nullable', 'array'],
            'columns'     => ['nullable', 'array'],
        ]);

        $reportType = $validated['report_type'];
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $groupBy = $validated['group_by'] ?? 'daily';
        $format = $validated['format'];
        $filters = $validated['filters'] ?? [];
        $columns = $validated['columns'] ?? [];

        $payload = $this->generateReportPayload($reportType, $dateFrom, $dateTo, $groupBy, $filters);

        $report = ReportExport::create([
            'report_uid'   => $this->generateReportUid(),
            'title'        => $validated['title'] ?: $this->defaultReportTitle($reportType, $dateFrom, $dateTo),
            'report_type'  => $reportType,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'group_by'     => $groupBy,
            'format'       => $format,
            'filters'      => $filters,
            'columns'      => $columns,
            'summary'      => $payload['summary'] ?? [],
            'status'       => ReportExport::STATUS_COMPLETED,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
        ]);

        if ($format === 'csv') {
            $report->update($this->createCsvExport($report, $payload));
        }

        if ($format === 'pdf') {
            $report->update($this->createPdfExport($report, $payload));
        }

        return redirect()
            ->route('admin.reports.show', $report->id)
            ->with('success', 'Report generated successfully.');
    }

    public function show(ReportExport $report)
    {
        $this->adminOnly();

        return view('admin.reports.show', [
            'report'      => $report->load('generatedBy'),
            'reportTypes' => $this->reportTypes(),
            'title'       => 'Report Details',
            'breadcrumb'  => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Reports', 'url' => route('admin.reports.index')],
                ['text' => 'Report Details', 'url' => route('admin.reports.show', $report->id)],
            ],
        ]);
    }

    public function edit(ReportExport $report)
    {
        $this->adminOnly();

        return view('admin.reports.edit', [
            'report'         => $report,
            'title'          => 'Edit Report',
            'reportTypes'    => $this->reportTypes(),
            'formats'        => $this->formats(),
            'groupByOptions' => $this->groupByOptions(),
            'campaigns'      => Campaign::query()->orderBy('title')->get(['id', 'title']),
            'products'       => Product::query()->orderBy('name')->get(['id', 'name']),
            'employees'      => User::query()->orderBy('name')->get(['id', 'name']),
            'breadcrumb'     => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Reports', 'url' => route('admin.reports.index')],
                ['text' => 'Edit Report', 'url' => route('admin.reports.edit', $report->id)],
            ],
        ]);
    }

    public function update(Request $request, ReportExport $report)
    {
        $this->adminOnly();

        $validated = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'report_type'     => ['required', 'string', 'in:' . implode(',', array_keys($this->reportTypes()))],
            'date_from'       => ['nullable', 'date'],
            'date_to'         => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by'        => ['nullable', 'string', 'in:' . implode(',', array_keys($this->groupByOptions()))],
            'format'          => ['required', 'string', 'in:html,csv,pdf,excel'],
            'status'          => ['required', 'string', 'in:pending,processing,completed,failed'],
            'filters'         => ['nullable', 'array'],
            'columns'         => ['nullable', 'array'],
            'error_message'   => ['nullable', 'string'],
            'regenerate_file' => ['nullable', 'boolean'],
        ]);

        $report->update([
            'title'         => $validated['title'],
            'report_type'   => $validated['report_type'],
            'date_from'     => $validated['date_from'] ?? null,
            'date_to'       => $validated['date_to'] ?? null,
            'group_by'      => $validated['group_by'] ?? null,
            'format'        => $validated['format'],
            'status'        => $validated['status'],
            'filters'       => $validated['filters'] ?? [],
            'columns'       => $validated['columns'] ?? [],
            'error_message' => $validated['error_message'] ?? null,
        ]);

        if ($request->boolean('regenerate_file')) {
            if ($report->file_path && Storage::disk($report->file_disk)->exists($report->file_path)) {
                Storage::disk($report->file_disk)->delete($report->file_path);
            }

            $payload = $this->generateReportPayload(
                $report->report_type,
                optional($report->date_from)->format('Y-m-d'),
                optional($report->date_to)->format('Y-m-d'),
                $report->group_by ?: 'daily',
                $report->filters ?? []
            );

            $fileData = [
                'file_name' => null,
                'file_path' => null,
                'file_disk' => $report->file_disk ?: 'public',
                'mime_type' => null,
                'file_size' => null,
            ];

            if ($report->format === 'csv') {
                $fileData = $this->createCsvExport($report, $payload);
            }

            if ($report->format === 'pdf') {
                $fileData = $this->createPdfExport($report, $payload);
            }

            $report->update(array_merge($fileData, [
                'summary'      => $payload['summary'] ?? [],
                'status'       => ReportExport::STATUS_COMPLETED,
                'generated_at' => now(),
            ]));
        }

        return redirect()
            ->route('admin.reports.show', $report->id)
            ->with('success', 'Report updated successfully.');
    }

    public function download(ReportExport $report)
    {
        $this->adminOnly();

        if (! $report->file_path || ! Storage::disk($report->file_disk)->exists($report->file_path)) {
            return back()->with('error', 'Report file not found.');
        }

        return Storage::disk($report->file_disk)
            ->download($report->file_path, $report->file_name);
    }

    public function destroy(ReportExport $report)
    {
        $this->adminOnly();

        $report->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Report moved to trash successfully.',
        ]);
    }

    public function trash(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse(
            $request,
            $this->reportQuery(true),
            'Report Trash Bin',
            true
        );
    }

    public function restore($id)
    {
        $this->adminOnly();

        ReportExport::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'status'  => true,
            'message' => 'Report restored successfully.',
        ]);
    }

    public function forceDelete($id)
    {
        $this->adminOnly();

        $report = ReportExport::onlyTrashed()->findOrFail($id);

        if ($report->file_path && Storage::disk($report->file_disk)->exists($report->file_path)) {
            Storage::disk($report->file_disk)->delete($report->file_path);
        }

        $report->forceDelete();

        return response()->json([
            'status'  => true,
            'message' => 'Report permanently deleted successfully.',
        ]);
    }

    public function multipleAction(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'action' => ['required', 'in:delete,restore,force_delete'],
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer'],
        ]);

        if ($request->action === 'delete') {
            ReportExport::whereIn('id', $request->ids)->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Selected reports moved to trash.',
            ]);
        }

        if ($request->action === 'restore') {
            ReportExport::onlyTrashed()->whereIn('id', $request->ids)->restore();

            return response()->json([
                'status'  => true,
                'message' => 'Selected reports restored.',
            ]);
        }

        if ($request->action === 'force_delete') {
            $reports = ReportExport::onlyTrashed()->whereIn('id', $request->ids)->get();

            foreach ($reports as $report) {
                if ($report->file_path && Storage::disk($report->file_disk)->exists($report->file_path)) {
                    Storage::disk($report->file_disk)->delete($report->file_path);
                }

                $report->forceDelete();
            }

            return response()->json([
                'status'  => true,
                'message' => 'Selected reports permanently deleted.',
            ]);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Invalid action selected.',
        ], 422);
    }

    private function generateReportPayload(string $reportType, ?string $dateFrom, ?string $dateTo, string $groupBy, array $filters): array
    {
        return match ($reportType) {
            'product_report'        => $this->productReportPayload($dateFrom, $dateTo, $filters),
            'tracking_pixel_report' => $this->trackingPixelReportPayload(),
            default                 => $this->orderBasedReportPayload($dateFrom, $dateTo, $groupBy, $filters),
        };
    }

    private function orderBaseQuery(?string $dateFrom, ?string $dateTo, array $filters): Builder
    {
        $query = Order::query();

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
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

        if (isset($filters['is_fake']) && $filters['is_fake'] !== '') {
            $query->where('is_fake', (bool) $filters['is_fake']);
        }

        if (! empty($filters['employee_id']) && Schema::hasColumn('orders', 'assigned_employee_id')) {
            $query->where('assigned_employee_id', $filters['employee_id']);
        }

        return $query;
    }

    private function orderBasedReportPayload(?string $dateFrom, ?string $dateTo, string $groupBy, array $filters): array
    {
        $query = $this->orderBaseQuery($dateFrom, $dateTo, $filters);

        return [
            'summary' => $this->orderSummary($query),
            'rows'    => $this->orderGroupedRows($query, $groupBy),
        ];
    }

    private function orderSummary(Builder $query): array
    {
        $statusCounts = (clone $query)
            ->select('order_status', DB::raw('COUNT(*) as total'))
            ->groupBy('order_status')
            ->pluck('total', 'order_status')
            ->toArray();

        return [
            'total_orders'      => (clone $query)->count(),
            'pending_orders'    => $statusCounts[Order::STATUS_PENDING] ?? 0,
            'confirmed_orders'  => $statusCounts[Order::STATUS_CONFIRMED] ?? 0,
            'processing_orders' => $statusCounts[Order::STATUS_PROCESSING] ?? 0,
            'shipped_orders'    => $statusCounts[Order::STATUS_SHIPPED] ?? 0,
            'delivered_orders'  => $statusCounts[Order::STATUS_DELIVERED] ?? 0,
            'cancelled_orders'  => $statusCounts[Order::STATUS_CANCELLED] ?? 0,
            'fake_orders'       => (clone $query)->where('is_fake', true)->count(),
            'gross_sales'       => (clone $query)->sum('total_amount'),
            'delivered_sales'   => (clone $query)->where('order_status', Order::STATUS_DELIVERED)->sum('total_amount'),
            'shipping_total'    => (clone $query)->sum('shipping_charge'),
            'cod_total'         => (clone $query)->sum('cod_charge'),
            'unique_customers'  => (clone $query)->distinct('phone')->count('phone'),
        ];
    }

    private function orderGroupedRows(Builder $query, string $groupBy): array
    {
        if ($groupBy === 'daily') {
            return (clone $query)
                ->selectRaw('DATE(created_at) as label, COUNT(*) as total_orders, SUM(total_amount) as total_sales')
                ->groupByRaw('DATE(created_at)')
                ->orderByRaw('DATE(created_at) DESC')
                ->get()
                ->toArray();
        }

        if ($groupBy === 'monthly') {
            return (clone $query)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as label, COUNT(*) as total_orders, SUM(total_amount) as total_sales")
                ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
                ->orderByRaw("DATE_FORMAT(created_at, '%Y-%m') DESC")
                ->get()
                ->toArray();
        }

        if ($groupBy === 'campaign') {
            return (clone $query)
                ->leftJoin('campaigns', 'orders.campaign_id', '=', 'campaigns.id')
                ->selectRaw("COALESCE(campaigns.title, 'No Campaign') as label, COUNT(orders.id) as total_orders, SUM(orders.total_amount) as total_sales")
                ->groupBy('orders.campaign_id', 'campaigns.title')
                ->orderByDesc('total_orders')
                ->get()
                ->toArray();
        }

        if ($groupBy === 'status') {
            return (clone $query)
                ->selectRaw('order_status as label, COUNT(*) as total_orders, SUM(total_amount) as total_sales')
                ->groupBy('order_status')
                ->orderByDesc('total_orders')
                ->get()
                ->toArray();
        }

        if ($groupBy === 'payment_status') {
            return (clone $query)
                ->selectRaw('payment_status as label, COUNT(*) as total_orders, SUM(total_amount) as total_sales')
                ->groupBy('payment_status')
                ->orderByDesc('total_orders')
                ->get()
                ->toArray();
        }

        if ($groupBy === 'delivery_area') {
            return (clone $query)
                ->selectRaw('delivery_area as label, COUNT(*) as total_orders, SUM(total_amount) as total_sales')
                ->groupBy('delivery_area')
                ->orderByDesc('total_orders')
                ->get()
                ->toArray();
        }

        return (clone $query)
            ->latest()
            ->limit(50)
            ->get([
                'invoice_id',
                'customer_name',
                'phone',
                'delivery_area',
                'payment_status',
                'order_status',
                'is_fake',
                'total_amount',
                'created_at',
            ])
            ->toArray();
    }

    private function productReportPayload(?string $dateFrom, ?string $dateTo, array $filters): array
    {
        if (! Schema::hasTable('order_items')) {
            return [
                'summary' => ['message' => 'order_items table not found.'],
                'rows'    => [],
            ];
        }

        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id');

        if ($dateFrom) {
            $query->whereDate('orders.created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('orders.created_at', '<=', $dateTo);
        }

        if (! empty($filters['product_id'])) {
            $query->where('order_items.product_id', $filters['product_id']);
        }

        if (! empty($filters['campaign_id'])) {
            $query->where('orders.campaign_id', $filters['campaign_id']);
        }

        $rows = $query
            ->selectRaw("
                COALESCE(order_items.product_name, products.name, 'Unknown Product') as label,
                COUNT(DISTINCT orders.id) as total_orders,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.total_price) as total_sales
            ")
            ->groupBy('order_items.product_id', 'order_items.product_name', 'products.name')
            ->orderByDesc('total_sales')
            ->get()
            ->toArray();

        return [
            'summary' => [
                'total_products' => count($rows),
                'total_quantity' => collect($rows)->sum('total_quantity'),
                'total_sales'    => collect($rows)->sum('total_sales'),
            ],
            'rows' => json_decode(json_encode($rows), true),
        ];
    }

    private function trackingPixelReportPayload(): array
    {
        $rows = TrackingPixel::query()
            ->selectRaw('platform as label, COUNT(*) as total_pixels, SUM(status = 1) as active_pixels, SUM(status = 0) as inactive_pixels')
            ->groupBy('platform')
            ->orderByDesc('total_pixels')
            ->get()
            ->toArray();

        return [
            'summary' => [
                'total_pixels'    => TrackingPixel::count(),
                'active_pixels'   => TrackingPixel::where('status', true)->count(),
                'inactive_pixels' => TrackingPixel::where('status', false)->count(),
            ],
            'rows' => $rows,
        ];
    }

    private function createCsvExport(ReportExport $report, array $payload): array
    {
        $fileName = $report->report_uid . '.csv';
        $filePath = 'reports/' . $fileName;

        $csvContent = $this->buildDesignedCsv($report, $payload);

        Storage::disk('public')->put($filePath, $csvContent);

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_disk' => 'public',
            'mime_type' => 'text/csv',
            'file_size' => Storage::disk('public')->size($filePath),
        ];
    }

    private function buildDesignedCsv(ReportExport $report, array $payload): string
    {
        $handle = fopen('php://temp', 'r+');

        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['Report Title', $report->title]);
        fputcsv($handle, ['Report UID', $report->report_uid]);
        fputcsv($handle, ['Report Type', $this->reportTypes()[$report->report_type] ?? $report->report_type]);
        fputcsv($handle, ['Date Range', ($report->date_from ?: 'Start') . ' to ' . ($report->date_to ?: 'Today')]);
        fputcsv($handle, ['Generated At', optional($report->generated_at)->format('d M Y, h:i A')]);
        fputcsv($handle, []);

        fputcsv($handle, ['SUMMARY']);

        foreach (($payload['summary'] ?? []) as $key => $value) {
            fputcsv($handle, [
                ucwords(str_replace('_', ' ', $key)),
                is_array($value) || is_object($value) ? json_encode($value) : $value,
            ]);
        }

        fputcsv($handle, []);
        fputcsv($handle, ['FILTERS']);

        foreach (($report->filters ?? []) as $key => $value) {
            fputcsv($handle, [
                ucwords(str_replace('_', ' ', $key)),
                is_array($value) || is_object($value) ? json_encode($value) : ($value ?: 'All'),
            ]);
        }

        fputcsv($handle, []);
        fputcsv($handle, ['DATA']);

        $rows = collect($payload['rows'] ?? [])
            ->map(fn ($row) => (array) $row)
            ->values()
            ->toArray();

        if (empty($rows)) {
            fputcsv($handle, ['No data found']);
        } else {
            fputcsv($handle, array_map(
                fn ($heading) => ucwords(str_replace('_', ' ', $heading)),
                array_keys($rows[0])
            ));

            foreach ($rows as $row) {
                fputcsv($handle, array_map(function ($value) {
                    if (is_array($value) || is_object($value)) {
                        return json_encode($value);
                    }

                    return $value;
                }, $row));
            }
        }

        rewind($handle);

        $csv = stream_get_contents($handle);

        fclose($handle);

        return $csv;
    }

    private function createPdfExport(ReportExport $report, array $payload): array
    {
        $fileName = $report->report_uid . '.pdf';
        $filePath = 'reports/' . $fileName;

        $pdf = Pdf::loadView('admin.reports.exports.pdf', [
            'report'      => $report,
            'payload'     => $payload,
            'reportTypes' => $this->reportTypes(),
        ])->setPaper('a4', 'portrait');

        Storage::disk('public')->put($filePath, $pdf->output());

        return [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_disk' => 'public',
            'mime_type' => 'application/pdf',
            'file_size' => Storage::disk('public')->size($filePath),
        ];
    }

    private function defaultReportTitle(string $reportType, ?string $dateFrom, ?string $dateTo): string
    {
        $title = $this->reportTypes()[$reportType] ?? 'Report';

        if ($dateFrom || $dateTo) {
            $title .= ' (' . ($dateFrom ?: 'Start') . ' to ' . ($dateTo ?: 'Today') . ')';
        }

        return $title;
    }

    private function generateReportUid(): string
    {
        do {
            $uid = 'RPT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
        } while (ReportExport::where('report_uid', $uid)->exists());

        return $uid;
    }
}