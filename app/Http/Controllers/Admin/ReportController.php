<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TodayReportSummaryService;
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
            'html' => 'HTML Preview',
            'csv'  => 'CSV Excel Format',
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
    | All card counts use the Bangladesh calendar day (12:00 AM - 11:59:59 PM)
    | while database timestamps are compared in UTC.
    */
    private function getReportSummaryStats(): array
    {
        /*
         * One shared source of truth keeps Report Management and Dashboard
         * perfectly aligned for the same 12:00 AM - 11:59:59 PM window.
         */
        return app(TodayReportSummaryService::class)->summary();
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

    private function normalizeProductFilterIds($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = str_contains($value, ',') ? explode(',', $value) : [$value];
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        return collect($value)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function cleanReportFilters(array $filters): array
    {
        $productIds = [];

        if (array_key_exists('product_ids', $filters)) {
            $productIds = $this->normalizeProductFilterIds($filters['product_ids']);
        } elseif (! empty($filters['product_id'])) {
            $productIds = $this->normalizeProductFilterIds($filters['product_id']);
        }

        unset($filters['product_id']);

        if (! empty($productIds)) {
            $filters['product_ids'] = $productIds;
        } else {
            unset($filters['product_ids']);
        }

        return collect($filters)
            ->reject(function ($value) {
                if (is_array($value)) {
                    return empty(array_filter($value, fn ($item) => $item !== null && $item !== ''));
                }

                return $value === null || $value === '';
            })
            ->toArray();
    }

    private function reportFilterLabels(array $filters): array
    {
        $labels = [];

        foreach ($filters as $key => $value) {
            $labels[$key] = $this->humanReadableFilterValue($key, $value);
        }

        return $labels;
    }

    private function humanReadableFilterValue(string $key, $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return 'All';
        }

        if ($key === 'product_ids' || $key === 'product_id') {
            $ids = $this->normalizeProductFilterIds($value);

            if (empty($ids)) {
                return 'All Products';
            }

            $names = Product::query()
                ->whereIn('id', $ids)
                ->orderBy('name')
                ->pluck('name')
                ->toArray();

            return ! empty($names) ? implode(', ', $names) : implode(', ', $ids);
        }

        if ($key === 'campaign_id' && $value) {
            return Campaign::query()->whereKey($value)->value('title') ?: (string) $value;
        }

        if ($key === 'employee_id' && $value) {
            return User::query()->whereKey($value)->value('name') ?: (string) $value;
        }

        if ($key === 'is_fake') {
            return (string) $value === '1' ? 'Fake Only' : ((string) $value === '0' ? 'Real Only' : 'All');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return ucwords(str_replace('_', ' ', (string) $value));
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        $validated = $request->validate([
            'title'                 => ['nullable', 'string', 'max:255'],
            'report_type'           => ['required', 'string', 'in:' . implode(',', array_keys($this->reportTypes()))],
            'date_from'             => ['nullable', 'date'],
            'date_to'               => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by'              => ['nullable', 'string', 'in:' . implode(',', array_keys($this->groupByOptions()))],
            'format'                => ['required', 'string', 'in:html,csv'],
            'filters'               => ['nullable', 'array'],
            'filters.product_ids'   => ['nullable', 'array'],
            'filters.product_ids.*' => ['nullable', 'integer', 'exists:products,id'],
            'columns'               => ['nullable', 'array'],
        ]);

        $reportType = $validated['report_type'];
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $groupBy = $validated['group_by'] ?? 'daily';
        $format = $validated['format'];
        $filters = $this->cleanReportFilters($validated['filters'] ?? []);
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

        return redirect()
            ->route('admin.reports.show', $report->id)
            ->with('success', 'Report generated successfully.');
    }



    public function show(ReportExport $report)
    {
        $this->adminOnly();

        $report->load('generatedBy');

        $payload = $this->generateReportPayload(
            $report->report_type,
            optional($report->date_from)->format('Y-m-d'),
            optional($report->date_to)->format('Y-m-d'),
            $report->group_by ?: 'daily',
            $report->filters ?? []
        );

        return view('admin.reports.show', [
            'report'       => $report,
            'reportTypes'  => $this->reportTypes(),
            'payload'      => $payload,
            'productRows'  => $payload['product_rows'] ?? ($report->report_type === ReportExport::TYPE_PRODUCT ? ($payload['rows'] ?? []) : []),
            'detailRows'   => $payload['detail_rows'] ?? [],
            'filterLabels' => $this->reportFilterLabels($report->filters ?? []),
            'title'        => 'Report Details',
            'breadcrumb'   => [
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
            'title'                 => ['required', 'string', 'max:255'],
            'report_type'           => ['required', 'string', 'in:' . implode(',', array_keys($this->reportTypes()))],
            'date_from'             => ['nullable', 'date'],
            'date_to'               => ['nullable', 'date', 'after_or_equal:date_from'],
            'group_by'              => ['nullable', 'string', 'in:' . implode(',', array_keys($this->groupByOptions()))],
            'format'                => ['required', 'string', 'in:html,csv'],
            'status'                => ['required', 'string', 'in:pending,processing,completed,failed'],
            'filters'               => ['nullable', 'array'],
            'filters.product_ids'   => ['nullable', 'array'],
            'filters.product_ids.*' => ['nullable', 'integer', 'exists:products,id'],
            'columns'               => ['nullable', 'array'],
            'error_message'         => ['nullable', 'string'],
            'regenerate_file'       => ['nullable', 'boolean'],
        ]);

        $filters = $this->cleanReportFilters($validated['filters'] ?? []);

        $report->update([
            'title'         => $validated['title'],
            'report_type'   => $validated['report_type'],
            'date_from'     => $validated['date_from'] ?? null,
            'date_to'       => $validated['date_to'] ?? null,
            'group_by'      => $validated['group_by'] ?? null,
            'format'        => $validated['format'],
            'status'        => $validated['status'],
            'filters'       => $filters,
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

        $productIds = $this->normalizeProductFilterIds($filters['product_ids'] ?? ($filters['product_id'] ?? []));

        if (! empty($productIds)) {
            $query->whereHas('items', function ($itemQuery) use ($productIds) {
                $itemQuery->whereIn('product_id', $productIds);
            });
        }

        if (! empty($filters['order_status'])) {
            $query->where('order_status', $filters['order_status']);

            /*
             * A custom Order List is an exclusive current bucket.
             */
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
            'summary'      => $this->orderSummary($query),
            'rows'         => $this->orderGroupedRows($query, $groupBy),
            'product_rows' => $this->orderProductSummaryRows($query),
            'detail_rows'  => $this->orderDetailedRows($query),
        ];
    }



    private function orderSummary(Builder $query): array
    {
        $workflowQuery = clone $query;

        if (Schema::hasColumn('orders', 'custom_order_list')) {
            $workflowQuery->whereNull('custom_order_list');
        }

        /*
         * Status totals are mutually exclusive current-state totals.
         */
        $statusCounts = (clone $workflowQuery)
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
            'fake_orders'       => (clone $workflowQuery)
                ->where(function (Builder $fakeQuery) {
                    $fakeQuery
                        ->where('is_fake', true)
                        ->orWhere(
                            'order_status',
                            Order::STATUS_FAKE
                        );
                })
                ->count(),
            'gross_sales'       => (clone $query)->sum('total_amount'),
            'delivered_sales'   => (clone $workflowQuery)
                ->where(
                    'order_status',
                    Order::STATUS_DELIVERED
                )
                ->sum('total_amount'),
            'shipping_total'    => (clone $query)->sum('shipping_charge'),
            'cod_total'         => (clone $query)->sum('cod_charge'),
            'unique_customers'  => (clone $query)
                ->distinct('phone')
                ->count('phone'),
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
            if (Schema::hasColumn('orders', 'custom_order_list')) {
                return (clone $query)
                    ->selectRaw(
                        "CASE
                            WHEN custom_order_list = 'order_list_1'
                                THEN 'order_list_1'
                            WHEN custom_order_list = 'order_list_2'
                                THEN 'order_list_2'
                            ELSE order_status
                        END as label,
                        COUNT(*) as total_orders,
                        SUM(total_amount) as total_sales"
                    )
                    ->groupByRaw(
                        "CASE
                            WHEN custom_order_list = 'order_list_1'
                                THEN 'order_list_1'
                            WHEN custom_order_list = 'order_list_2'
                                THEN 'order_list_2'
                            ELSE order_status
                        END"
                    )
                    ->orderByDesc('total_orders')
                    ->get()
                    ->toArray();
            }

            return (clone $query)
                ->selectRaw(
                    'order_status as label,
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_sales'
                )
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

    private function orderProductSummaryRows(Builder $query): array
    {
        if (! Schema::hasTable('order_items')) {
            return [];
        }

        $orderIds = (clone $query)->pluck('orders.id')->filter()->values();

        if ($orderIds->isEmpty()) {
            return [];
        }

        return DB::table('order_items')
            ->whereIn('order_items.order_id', $orderIds)
            ->leftJoin('products', 'order_items.product_id', '=', 'products.id')
            ->selectRaw("\n                COALESCE(order_items.product_name, products.name, 'Unknown Product') as product_name,\n                COUNT(DISTINCT order_items.order_id) as total_orders,\n                SUM(order_items.quantity) as total_quantity,\n                SUM(order_items.total_price) as total_sales\n            ")
            ->groupBy('order_items.product_id', 'order_items.product_name', 'products.name')
            ->orderByDesc('total_sales')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    private function reportColumnLabels(): array
    {
        return [
            'invoice_id'       => 'Invoice ID',
            'campaign'         => 'Campaign',
            'products'         => 'Products',
            'customer_name'    => 'Customer Name',
            'phone'            => 'Phone',
            'address'          => 'Address',
            'delivery_area'    => 'Delivery Area',
            'payment_status'   => 'Payment Status',
            'order_status'     => 'Order Status',
            'sub_total'        => 'Sub Total',
            'shipping_charge'  => 'Delivery Charge',
            'cod_charge'       => 'COD Charge',
            'total_amount'     => 'Total Amount',
            'assigned_employee'=> 'Employee',
            'admin_note'       => 'Admin Note',
            'customer_note'    => 'Customer Note',
            'created_at'       => 'Created At',
        ];
    }

    private function orderDetailedRows(Builder $query): array
    {
        $orders = (clone $query)
            ->with(['campaign', 'assignedEmployee', 'items.product'])
            ->latest()
            ->get();

        return $orders->map(function (Order $order) {
            return [
                'invoice_id'        => $order->invoice_id,
                'campaign'          => $order->campaign?->title ?? 'N/A',
                'products'          => $order->items->map(function ($item) {
                    $name = $item->product_name ?: ($item->product->name ?? 'Product');
                    return $name . ' x ' . (int) $item->quantity;
                })->implode(', '),
                'customer_name'     => $order->customer_name,
                'phone'             => $order->phone,
                'address'           => $order->address,
                'delivery_area'     => $order->delivery_area,
                'payment_status'    => $order->payment_status,
                'order_status'      => $order->custom_order_list
                    ?: $order->order_status,
                'sub_total'         => $order->sub_total,
                'shipping_charge'   => $order->shipping_charge,
                'cod_charge'        => $order->cod_charge,
                'total_amount'      => $order->total_amount,
                'assigned_employee' => $order->assignedEmployee?->name ?? 'Unassigned',
                'admin_note'        => $order->admin_note,
                'customer_note'     => $order->customer_note,
                'created_at'        => optional($order->created_at)->format('d M Y, h:i A'),
            ];
        })->toArray();
    }

    private function selectedReportColumns(array $columns = []): array
    {
        $available = $this->reportColumnLabels();

        if (empty($columns)) {
            return array_keys($available);
        }

        $columns = array_values(array_filter($columns, fn ($column) => array_key_exists($column, $available)));

        return empty($columns) ? array_keys($available) : $columns;
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

        $productIds = $this->normalizeProductFilterIds($filters['product_ids'] ?? ($filters['product_id'] ?? []));

        if (! empty($productIds)) {
            $query->whereIn('order_items.product_id', $productIds);
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

        if (isset($filters['is_fake']) && $filters['is_fake'] !== '') {
            $query->where('orders.is_fake', (bool) $filters['is_fake']);
        }

        if (! empty($filters['employee_id']) && Schema::hasColumn('orders', 'assigned_employee_id')) {
            $query->where('orders.assigned_employee_id', $filters['employee_id']);
        }

        $rows = $query
            ->selectRaw("\n                order_items.product_id as product_id,\n                COALESCE(order_items.product_name, products.name, 'Unknown Product') as product_name,\n                COUNT(DISTINCT orders.id) as total_orders,\n                SUM(order_items.quantity) as total_quantity,\n                SUM(order_items.total_price) as total_sales\n            ")
            ->groupBy('order_items.product_id', 'order_items.product_name', 'products.name')
            ->orderByDesc('total_sales')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return [
            'summary' => [
                'total_products' => count($rows),
                'total_orders'   => collect($rows)->sum('total_orders'),
                'total_quantity' => collect($rows)->sum('total_quantity'),
                'total_sales'    => collect($rows)->sum('total_sales'),
            ],
            'rows'         => $rows,
            'product_rows' => $rows,
            'detail_rows'  => $rows,
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

    private function csvValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private function writeCsvSection($handle, string $title, array $rows, ?array $columns = null): void
    {
        fputcsv($handle, []);
        fputcsv($handle, [$title]);

        if (empty($rows)) {
            fputcsv($handle, ['No data found']);
            return;
        }

        $firstRow = (array) $rows[0];
        $columns = $columns ?: array_keys($firstRow);

        fputcsv($handle, array_map(
            fn ($heading) => ucwords(str_replace('_', ' ', $heading)),
            $columns
        ));

        foreach ($rows as $row) {
            $row = (array) $row;
            fputcsv($handle, array_map(
                fn ($column) => $this->csvValue($row[$column] ?? ''),
                $columns
            ));
        }
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
            'mime_type' => 'application/vnd.ms-excel; charset=UTF-8',
            'file_size' => Storage::disk('public')->size($filePath),
        ];
    }



    private function buildDesignedCsv(ReportExport $report, array $payload): string
    {
        $handle = fopen('php://temp', 'r+');

        // UTF-8 BOM দিলে Bangla text Excel-এ readable থাকে।
        fputs($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['Report Title', $report->title]);
        fputcsv($handle, ['Report UID', $report->report_uid]);
        fputcsv($handle, ['Report Type', $this->reportTypes()[$report->report_type] ?? $report->report_type]);
        fputcsv($handle, ['Date Range', ($report->date_from ? optional($report->date_from)->format('d M Y') : 'Start') . ' to ' . ($report->date_to ? optional($report->date_to)->format('d M Y') : 'Today')]);
        fputcsv($handle, ['Group By', $report->group_by ?: 'Default']);
        fputcsv($handle, ['Generated By', optional($report->generatedBy)->name ?? auth()->user()?->name ?? 'System']);
        fputcsv($handle, ['Generated At', optional($report->generated_at)->format('d M Y, h:i A')]);

        fputcsv($handle, []);
        fputcsv($handle, ['SUMMARY']);

        foreach (($payload['summary'] ?? []) as $key => $value) {
            fputcsv($handle, [
                ucwords(str_replace('_', ' ', $key)),
                $this->csvValue($value),
            ]);
        }

        fputcsv($handle, []);
        fputcsv($handle, ['FILTERS']);

        if (empty($report->filters)) {
            fputcsv($handle, ['No filters applied']);
        } else {
            foreach (($report->filters ?? []) as $key => $value) {
                fputcsv($handle, [
                    ucwords(str_replace('_', ' ', $key)),
                    $this->humanReadableFilterValue($key, $value),
                ]);
            }
        }

        if (! empty($payload['product_rows'])) {
            $this->writeCsvSection($handle, 'PRODUCT SUMMARY', $payload['product_rows'], [
                'product_name',
                'total_orders',
                'total_quantity',
                'total_sales',
            ]);
        }

        $this->writeCsvSection($handle, 'REPORT DATA', $payload['rows'] ?? []);

        if (! empty($payload['detail_rows']) && $report->report_type !== ReportExport::TYPE_PRODUCT) {
            $selectedColumns = $this->selectedReportColumns($report->columns ?? []);
            $this->writeCsvSection($handle, 'DETAILED ORDERS', $payload['detail_rows'], $selectedColumns);
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
