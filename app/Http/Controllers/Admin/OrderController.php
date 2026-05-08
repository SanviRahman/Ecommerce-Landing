<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\OrderAssignmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\SteadfastCourierService;
use Throwable;

class OrderController extends Controller
{
    /**
     * Order status list.
     */
    private array $orderStatuses = [
        'pending',
        'confirmed',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
        'fake',
    ];

    /**
     * Payment status list.
     */
    private array $paymentStatuses = [
        'unpaid',
        'cod_pending',
        'collected',
        'failed',
    ];

    /**
     * Courier service list.
     */
    private function courierServices(): array
    {
        return config('couriers.list', []);
    }

    /**
     * Base query.
     *
     * Admin can see all orders.
     * Employee can see only assigned orders.
     */
    private function orderQuery(bool $trash = false): Builder
    {
        $query = $trash
            ? Order::onlyTrashed()
            : Order::query();

        $query->with([
            'campaign',
            'assignedEmployee',
            'items',
        ]);

        if (auth()->user()->isEmployee()) {
            $query->where('assigned_employee_id', auth()->id());
        }

        return $query;
    }

    /**
     * Check employee order access.
     */
    private function checkOrderAccess(Order $order): void
    {
        if (auth()->user()->isEmployee() && (int) $order->assigned_employee_id !== (int) auth()->id()) {
            abort(403, 'Unauthorized access.');
        }
    }

    /**
     * Apply search/filter.
     */
    private function applyFilters(Builder $query, Request $request, bool $ignoreOrderStatus = false): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_id', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('delivery_area', 'like', "%{$search}%")
                    ->orWhere('courier_service', 'like', "%{$search}%")
                    ->orWhere('customer_note', 'like', "%{$search}%")
                    ->orWhere('admin_note', 'like', "%{$search}%")
                    ->orWhereHas('assignedEmployee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (! $ignoreOrderStatus && $request->filled('order_status') && $request->order_status !== 'all') {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('courier_service') && $request->courier_service !== 'all') {
            $query->where('courier_service', $request->courier_service);
        }

        if ($request->filled('fake_status') && $request->fake_status !== 'all') {
            if ($request->fake_status === 'fake') {
                $query->where(function ($q) {
                    $q->where('is_fake', true)
                        ->orWhere('order_status', 'fake');
                });
            }

            if ($request->fake_status === 'real') {
                $query->where('is_fake', false)
                    ->where('order_status', '!=', 'fake');
            }
        }

        if ($request->filled('assigned_employee_id') && $request->assigned_employee_id !== 'all') {
            if ($request->assigned_employee_id === 'unassigned') {
                $query->whereNull('assigned_employee_id');
            } else {
                $query->where('assigned_employee_id', $request->assigned_employee_id);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    /**
     * Top small stats.
     */
    private function orderStats(Builder $query): array
    {
        return [
            'all'        => (clone $query)->count(),

            'processing' => (clone $query)
                ->where('order_status', 'processing')
                ->count(),

            'delivered'  => (clone $query)
                ->where('order_status', 'delivered')
                ->count(),

            'cancelled'  => (clone $query)
                ->where('order_status', 'cancelled')
                ->count(),

            'fake'       => (clone $query)
                ->where(function ($q) {
                    $q->where('is_fake', true)
                        ->orWhere('order_status', 'fake');
                })
                ->count(),
        ];
    }

    /**
     * Shared list response.
     */
    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $statsQuery = $this->applyFilters($this->orderQuery($isTrash), $request, true);
        $stats      = $this->orderStats($statsQuery);

        $query = $this->applyFilters($query, $request);

        $orders = $query->latest()->paginate(10);

        $employees = User::employees()
            ->active()
            ->orderBy('name')
            ->get();

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Orders', 'url' => route('admin.orders.index')],
        ];

        if ($isTrash) {
            $breadcrumb[] = [
                'text' => 'Trash',
                'url'  => route('admin.orders.trashed'),
            ];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'stats'  => $stats,
                'html'   => view('admin.orders.partials.table', [
                    'orders'          => $orders,
                    'isTrash'         => $isTrash,
                    'courierServices' => $this->courierServices(),
                ])->render(),
            ]);
        }

        return view('admin.orders.index', [
            'orders'          => $orders,
            'employees'       => $employees,
            'title'           => $title,
            'breadcrumb'      => $breadcrumb,
            'orderStatuses'   => $this->orderStatuses,
            'paymentStatuses' => $this->paymentStatuses,
            'courierServices' => $this->courierServices(),
            'isTrash'         => $isTrash,
            'stats'           => $stats,
        ]);
    }

    /**
     * All orders.
     */
    public function index(Request $request)
    {
        return $this->listResponse(
            $request,
            $this->orderQuery(),
            'Order Management'
        );
    }

    /**
     * Pending orders.
     */
    public function pending(Request $request)
    {
        $request->merge(['order_status' => 'pending']);

        return $this->listResponse(
            $request,
            $this->orderQuery(),
            'Pending Orders'
        );
    }

    /**
     * Confirmed orders.
     */
    public function confirmed(Request $request)
    {
        $request->merge(['order_status' => 'confirmed']);

        return $this->listResponse(
            $request,
            $this->orderQuery(),
            'Confirmed Orders'
        );
    }

    /**
     * Processing orders.
     */
    public function processing(Request $request)
    {
        $request->merge(['order_status' => 'processing']);

        return $this->listResponse(
            $request,
            $this->orderQuery(),
            'Processing Orders'
        );
    }

    /**
     * Shipped orders.
     */
    public function shipped(Request $request)
    {
        $request->merge(['order_status' => 'shipped']);

        return $this->listResponse(
            $request,
            $this->orderQuery(),
            'Shipped Orders'
        );
    }

    /**
     * Delivered orders.
     */
    public function delivered(Request $request)
    {
        $request->merge(['order_status' => 'delivered']);

        return $this->listResponse(
            $request,
            $this->orderQuery(),
            'Delivered Orders'
        );
    }

    /**
     * Cancelled orders.
     */
    public function cancelled(Request $request)
    {
        $request->merge(['order_status' => 'cancelled']);

        return $this->listResponse(
            $request,
            $this->orderQuery(),
            'Cancelled Orders'
        );
    }

    /**
     * Fake orders.
     */
    public function fake(Request $request)
    {
        $query = $this->orderQuery()
            ->where(function ($q) {
                $q->where('is_fake', true)
                    ->orWhere('order_status', 'fake');
            });

        return $this->listResponse(
            $request,
            $query,
            'Fake Orders'
        );
    }

    /**
     * Trash list.
     */
    public function trash(Request $request)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        return $this->listResponse(
            $request,
            $this->orderQuery(true),
            'Order Trash Bin',
            true
        );
    }

    /**
     * Show single order.
     */
    public function show(Order $order)
    {
        $this->checkOrderAccess($order);

        $order->load([
            'campaign',
            'assignedEmployee',
            'items.product',
            'statusLogs',
            'fakeLogs',
        ]);

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Orders', 'url' => route('admin.orders.index')],
            ['text' => 'Order Details', 'url' => route('admin.orders.show', $order->id)],
        ];

        return view('admin.orders.show', [
            'order'           => $order,
            'title'           => 'Order Details',
            'breadcrumb'      => $breadcrumb,
            'orderStatuses'   => $this->orderStatuses,
            'paymentStatuses' => $this->paymentStatuses,
            'courierServices' => $this->courierServices(),
        ]);
    }

    /**
     * Invoice.
     */
    public function invoice(Order $order)
    {
        $this->checkOrderAccess($order);

        $order->load([
            'campaign',
            'assignedEmployee',
            'items.product',
        ]);

        $siteSetting = SiteSetting::query()
            ->where('status', true)
            ->latest()
            ->first();

        return view('admin.orders.invoice', [
            'order'           => $order,
            'siteSetting'     => $siteSetting,
            'title'           => 'Order Invoice',
            'courierServices' => $this->courierServices(),
        ]);
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $this->checkOrderAccess($order);

        $request->validate([
            'order_status' => ['required', Rule::in($this->orderStatuses)],
            'note'         => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $order) {
            $updateData = [
                'order_status' => $request->order_status,
                'is_fake'      => $request->order_status === 'fake',
            ];

            if ($request->order_status === 'confirmed') {
                $updateData['confirmed_at'] = now();
            }

            if ($request->order_status === 'delivered') {
                $updateData['delivered_at'] = now();
            }

            if ($request->order_status === 'cancelled') {
                $updateData['cancelled_at'] = now();
            }

            if ($request->order_status === 'fake') {
                $updateData['marked_fake_at'] = now();
            }

            $order->update($updateData);

            if (method_exists($order, 'statusLogs')) {
                $order->statusLogs()->create([
                    'status'     => $request->order_status,
                    'note'       => $request->note,
                    'created_by' => auth()->id(),
                ]);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Order status updated successfully.',
            ]);
        });
    }

    /**
     * Update payment status.
     */
    public function updatePaymentStatus(Request $request, Order $order)
    {
        $this->checkOrderAccess($order);

        $request->validate([
            'payment_status' => ['required', Rule::in($this->paymentStatuses)],
        ]);

        $order->update([
            'payment_status' => $request->payment_status,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Payment status updated successfully.',
        ]);
    }

    /**
     * Update admin note from index page.
     */
    public function updateAdminNote(Request $request, Order $order)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $order->update([
            'admin_note' => $request->admin_note,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Admin note updated successfully.',
        ]);
    }

    /**
     * Mark as fake.
     */
    public function markAsFake(Request $request, Order $order)
    {
        $this->checkOrderAccess($order);

        $request->validate([
            'fake_reason' => ['required', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $order) {
            $order->update([
                'is_fake'        => true,
                'order_status'   => 'fake',
                'marked_fake_at' => now(),
            ]);

            if (method_exists($order, 'fakeLogs')) {
                $order->fakeLogs()->create([
                    'fake_reason' => $request->fake_reason,
                    'detected_by' => auth()->user()->isAdmin() ? 'Admin' : 'Employee',
                ]);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Order marked as fake successfully.',
            ]);
        });
    }

    /**
     * Restore fake order.
     */
    public function restoreFake(Order $order)
    {
        $this->checkOrderAccess($order);

        $order->update([
            'is_fake'        => false,
            'order_status'   => 'pending',
            'marked_fake_at' => null,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Fake order restored successfully.',
        ]);
    }

    /**
     * Soft delete.
     */
    public function destroy(Order $order)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $order->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Order moved to trash successfully.',
        ]);
    }

    /**
     * Restore from trash.
     */
    public function restore($id)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        Order::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'status'  => true,
            'message' => 'Order restored successfully.',
        ]);
    }

    /**
     * Permanent delete.
     */
    public function forceDelete($id)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $order = Order::onlyTrashed()->findOrFail($id);
        $order->forceDelete();

        return response()->json([
            'status'  => true,
            'message' => 'Order permanently deleted successfully.',
        ]);
    }

    /**
     * Bulk actions.
     */
    public function multipleAction(Request $request)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'action' => [
                'required',
                'string',
                'in:delete,restore,force_delete,mark_fake,restore_fake,status_pending,status_confirmed,status_processing,status_shipped,status_delivered,status_cancelled,status_fake',
            ],
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['required', 'integer'],
        ]);

        $action = $validated['action'];
        $ids    = $validated['ids'];

        return DB::transaction(function () use ($action, $ids) {
            if ($action === 'delete') {
                $updated = Order::query()
                    ->whereIn('id', $ids)
                    ->delete();

                return response()->json([
                    'status'  => true,
                    'message' => $updated . ' selected orders moved to trash successfully.',
                ]);
            }

            if ($action === 'restore') {
                $updated = Order::onlyTrashed()
                    ->whereIn('id', $ids)
                    ->restore();

                return response()->json([
                    'status'  => true,
                    'message' => $updated . ' selected orders restored successfully.',
                ]);
            }

            if ($action === 'force_delete') {
                $updated = Order::onlyTrashed()
                    ->whereIn('id', $ids)
                    ->forceDelete();

                return response()->json([
                    'status'  => true,
                    'message' => $updated . ' selected orders permanently deleted successfully.',
                ]);
            }

            if ($action === 'mark_fake') {
                $updated = Order::query()
                    ->whereIn('id', $ids)
                    ->update([
                        'is_fake'        => true,
                        'order_status'   => 'fake',
                        'marked_fake_at' => now(),
                        'updated_at'     => now(),
                    ]);

                return response()->json([
                    'status'  => true,
                    'message' => $updated . ' selected orders marked as fake successfully.',
                ]);
            }

            if ($action === 'restore_fake') {
                $updated = Order::query()
                    ->whereIn('id', $ids)
                    ->update([
                        'is_fake'        => false,
                        'order_status'   => 'pending',
                        'marked_fake_at' => null,
                        'updated_at'     => now(),
                    ]);

                return response()->json([
                    'status'  => true,
                    'message' => $updated . ' selected fake orders restored successfully.',
                ]);
            }

            $statusMap = [
                'status_pending'    => 'pending',
                'status_confirmed'  => 'confirmed',
                'status_processing' => 'processing',
                'status_shipped'    => 'shipped',
                'status_delivered'  => 'delivered',
                'status_cancelled'  => 'cancelled',
                'status_fake'       => 'fake',
            ];

            if (array_key_exists($action, $statusMap)) {
                $status = $statusMap[$action];

                $updateData = [
                    'order_status' => $status,
                    'is_fake'      => $status === 'fake',
                    'updated_at'   => now(),
                ];

                if ($status === 'confirmed') {
                    $updateData['confirmed_at'] = now();
                }

                if ($status === 'delivered') {
                    $updateData['delivered_at'] = now();
                }

                if ($status === 'cancelled') {
                    $updateData['cancelled_at'] = now();
                }

                if ($status === 'fake') {
                    $updateData['marked_fake_at'] = now();
                } else {
                    $updateData['marked_fake_at'] = null;
                }

                $updated = Order::query()
                    ->whereIn('id', $ids)
                    ->update($updateData);

                return response()->json([
                    'status'  => true,
                    'message' => $updated . ' selected orders status updated successfully.',
                ]);
            }

            return response()->json([
                'status'  => false,
                'message' => 'Invalid bulk action selected.',
            ], 422);
        });
    }
    /**
     * Assign old unassigned orders to employees.
     */
    public function assignUnassignedOrders()
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $orders = Order::query()
            ->whereNull('assigned_employee_id')
            ->latest()
            ->get();

        $assignedCount = 0;

        foreach ($orders as $order) {
            $employee = app(OrderAssignmentService::class)->assign($order);

            if ($employee) {
                $assignedCount++;
            }
        }

        return response()->json([
            'status'  => true,
            'message' => "{$assignedCount} unassigned orders assigned successfully.",
        ]);
    }

    /**
     * Selected invoice print.
     */
    public function selectedInvoices(Request $request)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $orders = Order::query()
            ->with([
                'campaign',
                'assignedEmployee',
                'items.product',
            ])
            ->whereIn('id', $request->ids)
            ->latest()
            ->get();

        $siteSetting = SiteSetting::query()
            ->where('status', true)
            ->latest()
            ->first();

        return view('admin.orders.multiple-invoices', [
            'orders'          => $orders,
            'siteSetting'     => $siteSetting,
            'title'           => 'Selected Order Invoices',
            'courierServices' => $this->courierServices(),
        ]);
    }

    /**
     * Download invoice as PDF.
     */
    public function downloadInvoice(Order $order)
    {
        $this->checkOrderAccess($order);

        $order->load([
            'campaign',
            'assignedEmployee',
            'items.product',
        ]);

        $siteSetting = SiteSetting::query()
            ->where('status', true)
            ->latest()
            ->first();

        $fileName = 'invoice-' . $order->invoice_id . '.pdf';

        $pdf = Pdf::loadView('admin.orders.invoice-pdf', [
            'order'           => $order,
            'siteSetting'     => $siteSetting,
            'title'           => 'Order Invoice',
            'courierServices' => $this->courierServices(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($fileName);
    }

    /**
     * Send single order to SteadFast courier.
     */
    public function sendToSteadfast(Order $order, SteadfastCourierService $steadfast)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        try {
            $data = $steadfast->createOrder($order);

            return response()->json([
                'status'  => true,
                'message' => data_get($data, 'message', 'Order sent to SteadFast successfully.'),
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

/**
 * Send selected orders to SteadFast courier.
 */
    public function bulkSendToSteadfast(Request $request, SteadfastCourierService $steadfast)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $orders = Order::query()
            ->with(['items'])
            ->whereIn('id', $request->ids)
            ->where('courier_service', 'steadfast')
            ->get();

        $success = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($orders as $order) {
            try {
                $steadfast->createOrder($order);
                $success++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = '#' . $order->invoice_id . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'status'  => true,
            'message' => "SteadFast send completed. Success: {$success}, Failed: {$failed}",
            'errors' => $errors,
        ]);
    }

/**
 * Sync single order SteadFast status.
 */
    public function syncSteadfastStatus(Order $order, SteadfastCourierService $steadfast)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        try {
            $data = $steadfast->syncStatus($order);

            return response()->json([
                'status'          => true,
                'message'         => 'SteadFast status synced successfully.',
                'delivery_status' => data_get($data, 'delivery_status'),
                'data'            => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

/**
 * Check SteadFast current balance.
 */
    public function steadfastBalance(SteadfastCourierService $steadfast)
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        try {
            $data = $steadfast->getBalance();

            return response()->json([
                'status'  => true,
                'message' => 'Balance fetched successfully.',
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
