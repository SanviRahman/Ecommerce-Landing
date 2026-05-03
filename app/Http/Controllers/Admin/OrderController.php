<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderAssignmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_id', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('delivery_area', 'like', "%{$search}%")
                    ->orWhere('customer_note', 'like', "%{$search}%")
                    ->orWhere('admin_note', 'like', "%{$search}%")
                    ->orWhereHas('assignedEmployee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('order_status') && $request->order_status !== 'all') {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
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
     * Shared list response.
     */
    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
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
                'url' => route('admin.orders.trashed'),
            ];
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html' => view('admin.orders.partials.table', [
                    'orders' => $orders,
                    'isTrash' => $isTrash,
                ])->render(),
            ]);
        }

        return view('admin.orders.index', [
            'orders' => $orders,
            'employees' => $employees,
            'title' => $title,
            'breadcrumb' => $breadcrumb,
            'orderStatuses' => $this->orderStatuses,
            'paymentStatuses' => $this->paymentStatuses,
            'isTrash' => $isTrash,
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
            'order' => $order,
            'title' => 'Order Details',
            'breadcrumb' => $breadcrumb,
            'orderStatuses' => $this->orderStatuses,
            'paymentStatuses' => $this->paymentStatuses,
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

        return view('admin.orders.invoice', [
            'order' => $order,
            'title' => 'Order Invoice',
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
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $order) {
            $updateData = [
                'order_status' => $request->order_status,
                'is_fake' => $request->order_status === 'fake',
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
                    'status' => $request->order_status,
                    'note' => $request->note,
                    'created_by' => auth()->id(),
                ]);
            }

            return response()->json([
                'status' => true,
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
            'status' => true,
            'message' => 'Payment status updated successfully.',
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
                'is_fake' => true,
                'order_status' => 'fake',
                'marked_fake_at' => now(),
            ]);

            if (method_exists($order, 'fakeLogs')) {
                $order->fakeLogs()->create([
                    'fake_reason' => $request->fake_reason,
                    'detected_by' => auth()->user()->isAdmin() ? 'Admin' : 'Employee',
                ]);
            }

            return response()->json([
                'status' => true,
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
            'is_fake' => false,
            'order_status' => 'pending',
            'marked_fake_at' => null,
        ]);

        return response()->json([
            'status' => true,
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
            'status' => true,
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
            'status' => true,
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
            'status' => true,
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

        $request->validate([
            'action' => ['required', 'in:delete,restore,force_delete,mark_fake,restore_fake'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->action;
        $ids = $request->ids;

        if ($action === 'delete') {
            Order::whereIn('id', $ids)->delete();

            return response()->json([
                'status' => true,
                'message' => 'Selected orders moved to trash successfully.',
            ]);
        }

        if ($action === 'restore') {
            Order::onlyTrashed()->whereIn('id', $ids)->restore();

            return response()->json([
                'status' => true,
                'message' => 'Selected orders restored successfully.',
            ]);
        }

        if ($action === 'force_delete') {
            Order::onlyTrashed()->whereIn('id', $ids)->forceDelete();

            return response()->json([
                'status' => true,
                'message' => 'Selected orders permanently deleted successfully.',
            ]);
        }

        if ($action === 'mark_fake') {
            Order::whereIn('id', $ids)->update([
                'is_fake' => true,
                'order_status' => 'fake',
                'marked_fake_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Selected orders marked as fake successfully.',
            ]);
        }

        if ($action === 'restore_fake') {
            Order::whereIn('id', $ids)->update([
                'is_fake' => false,
                'order_status' => 'pending',
                'marked_fake_at' => null,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Selected fake orders restored successfully.',
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid bulk action selected.',
        ], 422);
    }

    /**
     * Assign old unassigned orders to employees.
     *
     * New orders auto assign হবে Order model booted event দিয়ে.
     * এই method শুধু পুরনো unassigned orders assign করার জন্য।
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
            'status' => true,
            'message' => "{$assignedCount} unassigned orders assigned successfully.",
        ]);
    }
}