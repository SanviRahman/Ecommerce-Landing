<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\OrderAssignmentService;
use App\Services\PathaoCourierService;
use App\Services\SteadfastCourierService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

class OrderController extends Controller
{
    private function adminOnly(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function adminOrEmployeeOnly(): void
    {
        if (! auth()->check() || (! auth()->user()->isAdmin() && ! auth()->user()->isEmployee())) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function orderQuery(bool $trash = false): Builder
    {
        $query = $trash
            ? Order::onlyTrashed()
            : Order::query();

        return $query
            ->with([
                'campaign',
                'assignedEmployee',
                'items',
                'courierAccount',
            ])
            ->forLoggedInUser()
            ->latest();
    }

    private function getOrderStatuses(): array
    {
        return [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_CANCELLED,
            Order::STATUS_FAKE,
        ];
    }

    private function getPaymentStatuses(): array
    {
        return [
            Order::PAYMENT_STATUS_COD_PENDING,
            Order::PAYMENT_STATUS_COLLECTED,
            Order::PAYMENT_STATUS_FAILED,
            Order::PAYMENT_STATUS_UNPAID,
        ];
    }

    private function getCourierServices(): array
    {
        $couriers = CourierAccount::query()
            ->active()
            ->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();

        return array_merge([
            'none' => 'No Courier',
        ], $couriers);
    }

    private function getStats(): array
    {
        $baseQuery = Order::query()->forLoggedInUser();

        return [
            'all' => (clone $baseQuery)->count(),

            'processing' => (clone $baseQuery)
                ->where('order_status', Order::STATUS_PROCESSING)
                ->count(),

            'delivered' => (clone $baseQuery)
                ->where('order_status', Order::STATUS_DELIVERED)
                ->count(),

            'cancelled' => (clone $baseQuery)
                ->where('order_status', Order::STATUS_CANCELLED)
                ->count(),

            'fake' => (clone $baseQuery)
                ->where(function ($query) {
                    $query->where('is_fake', true)
                        ->orWhere('order_status', Order::STATUS_FAKE);
                })
                ->count(),
        ];
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_id', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('courier_service', 'like', "%{$search}%")
                    ->orWhereHas('assignedEmployee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('courierAccount', function ($courierQuery) use ($search) {
                        $courierQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('order_status') && $request->order_status !== 'all') {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('delivery_area') && $request->delivery_area !== 'all') {
            $query->where('delivery_area', $request->delivery_area);
        }

        if ($request->filled('courier_service') && $request->courier_service !== 'all') {
            if ($request->courier_service === 'none') {
                $query->whereNull('courier_service')
                    ->whereNull('courier_account_id');
            } else {
                $query->where('courier_service', $request->courier_service);
            }
        }

        if ($request->filled('courier_account_id') && $request->courier_account_id !== 'all') {
            if ($request->courier_account_id === 'none') {
                $query->whereNull('courier_account_id');
            } else {
                $query->where('courier_account_id', $request->courier_account_id);
            }
        }

        if ($request->filled('fake_status') && $request->fake_status !== 'all') {
            if ($request->fake_status === 'fake') {
                $query->where(function ($q) {
                    $q->where('is_fake', true)
                        ->orWhere('order_status', Order::STATUS_FAKE);
                });
            }

            if ($request->fake_status === 'real') {
                $query->where(function ($q) {
                    $q->where('is_fake', false)
                        ->orWhereNull('is_fake');
                })->where('order_status', '!=', Order::STATUS_FAKE);
            }
        }

        if ($request->filled('assigned_employee_id') && $request->assigned_employee_id !== 'all') {
            if ($request->assigned_employee_id === 'unassigned') {
                $query->whereNull('assigned_employee_id');
            } else {
                $query->where('assigned_employee_id', $request->assigned_employee_id);
            }
        }

        if ($request->filled('employee_id') && $request->employee_id !== 'all') {
            if ($request->employee_id === 'unassigned') {
                $query->whereNull('assigned_employee_id');
            } else {
                $query->where('assigned_employee_id', $request->employee_id);
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

    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $query = $this->applyFilters($query, $request);

        $orders = $query->paginate(20)->withQueryString();

        $employees = User::query()
            ->where('role', 'employee')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $courierAccounts = CourierAccount::query()
            ->active()
            ->orderBy('name')
            ->get();

        $defaultCourier = CourierAccount::defaultActive();

        $stats = $this->getStats();
        $orderStatuses = $this->getOrderStatuses();
        $paymentStatuses = $this->getPaymentStatuses();
        $courierServices = $this->getCourierServices();

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'stats' => $stats,
                'html' => view('admin.orders.partials.table', [
                    'orders' => $orders,
                    'isTrash' => $isTrash,
                    'defaultCourier' => $defaultCourier,
                    'courierServices' => $courierServices,
                ])->render(),
            ]);
        }

        return view('admin.orders.index', [
            'title' => $title,
            'orders' => $orders,
            'employees' => $employees,
            'courierAccounts' => $courierAccounts,
            'courierServices' => $courierServices,
            'defaultCourier' => $defaultCourier,
            'stats' => $stats,
            'orderStatuses' => $orderStatuses,
            'paymentStatuses' => $paymentStatuses,
            'isTrash' => $isTrash,
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => $title, 'url' => url()->current()],
            ],
        ]);
    }

    public function index(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery(), 'Order Manage');
    }

    public function pending(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->pending(), 'Pending Orders');
    }

    public function confirmed(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->confirmed(), 'Confirmed Orders');
    }

    public function processing(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->processing(), 'Processing Orders');
    }

    public function shipped(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->shipped(), 'Shipped Orders');
    }

    public function delivered(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->delivered(), 'Delivered Orders');
    }

    public function cancelled(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->cancelled(), 'Cancelled Orders');
    }

    public function fake(Request $request)
    {
        $this->adminOrEmployeeOnly();

        return $this->listResponse($request, $this->orderQuery()->fake(), 'Fake Orders');
    }

    public function trash(Request $request)
    {
        $this->adminOnly();

        return $this->listResponse($request, $this->orderQuery(true), 'Order Trash', true);
    }

    public function show(Order $order)
    {
        $this->adminOrEmployeeOnly();

        if (auth()->user()->isEmployee() && $order->assigned_employee_id !== auth()->id()) {
            abort(403, 'Unauthorized access.');
        }

        $order->load([
            'campaign',
            'assignedEmployee',
            'items',
            'statusLogs',
            'fakeLogs',
            'courierAccount',
        ]);

        return view('admin.orders.show', [
            'title' => 'Order Details',
            'order' => $order,
            'courierAccounts' => CourierAccount::query()
                ->active()
                ->orderBy('name')
                ->get(),
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Orders', 'url' => route('admin.orders.index')],
                ['text' => $order->invoice_id, 'url' => route('admin.orders.show', $order->id)],
            ],
        ]);
    }

    public function updateCourier(Request $request, Order $order)
    {
        $this->adminOnly();

        $request->validate([
            'courier_account_id' => ['nullable', 'exists:courier_accounts,id'],
        ]);

        $courier = null;

        if ($request->filled('courier_account_id')) {
            $courier = CourierAccount::query()
                ->active()
                ->findOrFail($request->courier_account_id);
        }

        $order->update([
            'courier_account_id' => $courier?->id,
            'courier_service' => $courier?->code,
        ]);

        return response()->json([
            'status' => true,
            'message' => $courier
                ? 'Courier selected successfully: ' . $courier->name
                : 'Courier removed successfully.',
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();

        $request->validate([
            'order_status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = $request->order_status;

        $updateData = [
            'order_status' => $status,
        ];

        if ($status === Order::STATUS_CONFIRMED) {
            $updateData['confirmed_at'] = now();
        }

        if ($status === Order::STATUS_DELIVERED) {
            $updateData['delivered_at'] = now();
        }

        if ($status === Order::STATUS_CANCELLED) {
            $updateData['cancelled_at'] = now();
        }

        if ($status === Order::STATUS_FAKE) {
            $updateData['is_fake'] = true;
            $updateData['marked_fake_at'] = now();
        }

        $order->update($updateData);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'status' => $status,
            'note' => $request->note,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order status updated successfully.',
        ]);
    }

    public function updatePaymentStatus(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();

        $request->validate([
            'payment_status' => ['required', 'string'],
        ]);

        $order->update([
            'payment_status' => $request->payment_status,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Payment status updated successfully.',
        ]);
    }

    public function updateAdminNote(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();

        $request->validate([
            'admin_note' => ['nullable', 'string'],
        ]);

        $order->update([
            'admin_note' => $request->admin_note,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Admin note updated successfully.',
        ]);
    }

    public function markAsFake(Request $request, Order $order)
    {
        $this->adminOrEmployeeOnly();

        $request->validate([
            'fake_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $order->update([
            'is_fake' => true,
            'order_status' => Order::STATUS_FAKE,
            'marked_fake_at' => now(),
        ]);

        $order->fakeLogs()->create([
            'fake_reason' => $request->fake_reason ?: 'Marked manually',
            'detected_by' => 'manual',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Order marked as fake.',
        ]);
    }

    public function restoreFake(Order $order)
    {
        $this->adminOrEmployeeOnly();

        $order->update([
            'is_fake' => false,
            'order_status' => Order::STATUS_PENDING,
            'marked_fake_at' => null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Fake order restored successfully.',
        ]);
    }

    public function assignUnassignedOrders()
    {
        $this->adminOnly();

        $count = app(OrderAssignmentService::class)->assignUnassigned();

        return response()->json([
            'status' => true,
            'message' => "{$count} orders assigned successfully.",
        ]);
    }

    public function selectedInvoices(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $orders = Order::query()
            ->whereIn('id', $request->ids)
            ->with([
                'items',
                'courierAccount',
                'assignedEmployee',
            ])
            ->get();

        $siteSetting = SiteSetting::query()
            ->where('status', true)
            ->latest()
            ->first();

        $courierServices = CourierAccount::query()
            ->active()
            ->pluck('name', 'code')
            ->toArray();

        return view('admin.orders.multiple-invoices', [
            'title' => 'Selected Invoices',
            'orders' => $orders,
            'siteSetting' => $siteSetting,
            'courierServices' => $courierServices,
        ]);
    }

    public function invoice(Order $order)
    {
        $this->adminOrEmployeeOnly();

        $order->load(['items', 'campaign', 'courierAccount', 'assignedEmployee']);

        return view('admin.orders.invoice', [
            'order' => $order,
            'title' => 'Invoice - ' . $order->invoice_id,
            'courierServices' => $this->getCourierServices(),
        ]);
    }

    public function downloadInvoice(Order $order)
    {
        $this->adminOrEmployeeOnly();

        $order->load(['items', 'campaign', 'courierAccount', 'assignedEmployee']);

        $pdf = Pdf::loadView('admin.orders.invoice-pdf', [
            'order' => $order,
            'siteSetting' => SiteSetting::query()
                ->where('status', true)
                ->latest()
                ->first(),
            'courierServices' => $this->getCourierServices(),
        ]);

        return $pdf->download($order->invoice_id . '.pdf');
    }

    public function sendToSteadfast(Order $order, SteadfastCourierService $steadfastCourierService)
    {
        $this->adminOnly();

        $order->loadMissing(['courierAccount', 'items']);

        if (! $order->courier_account_id) {
            return response()->json([
                'status' => false,
                'message' => 'Please select courier from order details page first.',
            ], 422);
        }

        if ($order->courierAccount?->code !== 'steadfast') {
            return response()->json([
                'status' => false,
                'message' => 'Selected courier is not SteadFast.',
            ], 422);
        }

        try {
            $data = $steadfastCourierService->createOrder($order);

            return response()->json([
                'status' => true,
                'message' => data_get($data, 'message', 'Order sent to SteadFast successfully.'),
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function bulkSendToSteadfast(Request $request, SteadfastCourierService $steadfastCourierService)
    {
        $this->adminOnly();

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $orders = Order::query()
            ->with(['courierAccount', 'items'])
            ->whereIn('id', $request->ids)
            ->whereHas('courierAccount', function ($query) {
                $query->where('code', 'steadfast');
            })
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Selected orders do not have SteadFast courier selected.',
            ], 422);
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($orders as $order) {
            try {
                $steadfastCourierService->createOrder($order);
                $success++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = $order->invoice_id . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'status' => true,
            'message' => "Bulk send completed. Success: {$success}, Failed: {$failed}",
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ]);
    }

    public function sendToPathao(Order $order, PathaoCourierService $pathaoCourierService)
    {
        $this->adminOnly();

        $order->loadMissing(['courierAccount', 'items']);

        if (! $order->courier_account_id) {
            return response()->json([
                'status' => false,
                'message' => 'Please select courier from order details page first.',
            ], 422);
        }

        if ($order->courierAccount?->code !== 'pathao') {
            return response()->json([
                'status' => false,
                'message' => 'Selected courier is not Pathao.',
            ], 422);
        }

        try {
            $data = $pathaoCourierService->createOrder($order);

            return response()->json([
                'status' => true,
                'message' => data_get($data, 'message', 'Order sent to Pathao successfully.'),
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function bulkSendToPathao(Request $request, PathaoCourierService $pathaoCourierService)
    {
        $this->adminOnly();

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $orders = Order::query()
            ->with(['courierAccount', 'items'])
            ->whereIn('id', $request->ids)
            ->whereHas('courierAccount', function ($query) {
                $query->where('code', 'pathao');
            })
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Selected orders do not have Pathao courier selected.',
            ], 422);
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($orders as $order) {
            try {
                $pathaoCourierService->createOrder($order);
                $success++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = $order->invoice_id . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'status' => true,
            'message' => "Pathao bulk send completed. Success: {$success}, Failed: {$failed}",
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ]);
    }

    public function syncSteadfastStatus(Order $order, SteadfastCourierService $steadfastCourierService)
    {
        $this->adminOnly();

        $order->loadMissing('courierAccount');

        if (! $order->courier_account_id) {
            return response()->json([
                'status' => false,
                'message' => 'Please select courier first.',
            ], 422);
        }

        if ($order->courierAccount?->code !== 'steadfast') {
            return response()->json([
                'status' => false,
                'message' => 'Selected courier is not SteadFast.',
            ], 422);
        }

        try {
            $data = $steadfastCourierService->syncStatus($order);

            return response()->json([
                'status' => true,
                'message' => 'SteadFast status synced successfully.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function steadfastBalance(SteadfastCourierService $steadfastCourierService)
    {
        $this->adminOnly();

        $courier = CourierAccount::query()
            ->active()
            ->where('code', 'steadfast')
            ->default()
            ->latest()
            ->first();

        try {
            $data = $steadfastCourierService->getBalance($courier);

            return response()->json([
                'status' => true,
                'message' => 'Balance fetched successfully.',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(Order $order)
    {
        $this->adminOnly();

        $order->delete();

        return response()->json([
            'status' => true,
            'message' => 'Order moved to trash successfully.',
        ]);
    }

    public function restore($id)
    {
        $this->adminOnly();

        Order::onlyTrashed()->findOrFail($id)->restore();

        return response()->json([
            'status' => true,
            'message' => 'Order restored successfully.',
        ]);
    }

    public function forceDelete($id)
    {
        $this->adminOnly();

        Order::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'Order permanently deleted successfully.',
        ]);
    }

    public function multipleAction(Request $request)
    {
        $this->adminOnly();

        $request->validate([
            'action' => ['required', 'string'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->action;
        $ids = $request->ids;

        if ($action === 'delete') {
            Order::whereIn('id', $ids)->delete();

            return response()->json([
                'status' => true,
                'message' => 'Selected orders moved to trash.',
            ]);
        }

        if ($action === 'restore') {
            Order::onlyTrashed()->whereIn('id', $ids)->restore();

            return response()->json([
                'status' => true,
                'message' => 'Selected orders restored.',
            ]);
        }

        if ($action === 'force_delete') {
            Order::onlyTrashed()->whereIn('id', $ids)->forceDelete();

            return response()->json([
                'status' => true,
                'message' => 'Selected orders permanently deleted.',
            ]);
        }

        if (str_starts_with($action, 'status_')) {
            $action = str_replace('status_', '', $action);
        }

        $allowedStatuses = $this->getOrderStatuses();

        if (in_array($action, $allowedStatuses, true)) {
            Order::whereIn('id', $ids)->update([
                'order_status' => $action,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Selected orders status updated.',
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid action selected.',
        ], 422);
    }
}