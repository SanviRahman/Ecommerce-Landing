<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedCustomer;
use App\Models\Order;
use App\Services\OrderBlockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BlockedCustomerController extends Controller
{
    private function adminOrEmployeeOnly(): void
    {
        if (! auth()->check() || (! auth()->user()->isAdmin() && ! auth()->user()->isEmployee())) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function ensureEmployeeOrderAccess(Order $order): void
    {
        if (auth()->user()?->isEmployee() && (int) $order->assigned_employee_id !== (int) auth()->id()) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function blockedCustomerQuery(bool $trash = false): Builder
    {
        $query = $trash
            ? BlockedCustomer::onlyTrashed()
            : BlockedCustomer::query();

        return $query
            ->with([
                'sourceOrder:id,invoice_id,assigned_employee_id',
                'blockedBy:id,name,email',
                'unblockedBy:id,name,email',
            ])
            ->latest('id');
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery
                    ->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('sourceOrder', function (Builder $orderQuery) use ($search) {
                        $orderQuery->where('invoice_id', 'like', "%{$search}%");
                    })
                    ->orWhereHas('blockedBy', function (Builder $userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('block_type') && $request->input('block_type') !== 'all') {
            match ($request->input('block_type')) {
                'phone' => $query->where('block_phone', true)->where('block_ip', false),
                'ip'    => $query->where('block_phone', false)->where('block_ip', true),
                'both'  => $query->where('block_phone', true)->where('block_ip', true),
                default => $query,
            };
        }

        return $query;
    }

    private function listResponse(Request $request, Builder $query, string $title, bool $isTrash = false)
    {
        $this->adminOrEmployeeOnly();

        $blockedCustomers = $this->applyFilters($query, $request)
            ->paginate(15)
            ->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html'   => view('admin.blocked-customers.partials.table', [
                    'blockedCustomers' => $blockedCustomers,
                    'isTrash'          => $isTrash,
                ])->render(),
            ]);
        }

        return view('admin.blocked-customers.index', [
            'title'            => $title,
            'blockedCustomers' => $blockedCustomers,
            'isTrash'          => $isTrash,
            'breadcrumb'       => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Blocked Customers', 'url' => route('admin.blocked-customers.index')],
                ...($isTrash ? [['text' => 'Trash', 'url' => route('admin.blocked-customers.trash')]] : []),
            ],
        ]);
    }

    public function index(Request $request)
    {
        return $this->listResponse(
            $request,
            $this->blockedCustomerQuery(),
            'Blocked Customers'
        );
    }

    public function trash(Request $request)
    {
        return $this->listResponse(
            $request,
            $this->blockedCustomerQuery(true),
            'Blocked Customers Trash',
            true
        );
    }

    public function create(Request $request)
    {
        $this->adminOrEmployeeOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.blocked-customers.index');
        }

        return response()->json([
            'status' => true,
            'html'   => view('admin.blocked-customers.partials.form', [
                'blockedCustomer' => null,
                'isEdit'          => false,
                'action'          => route('admin.blocked-customers.store'),
            ])->render(),
        ]);
    }

    public function store(Request $request, OrderBlockService $orderBlockService)
    {
        $this->adminOrEmployeeOnly();

        $data = $this->validateBlockPayload($request, $orderBlockService);
        $orderBlockService->createBlock($data, auth()->user());

        return response()->json([
            'status'  => true,
            'message' => 'Customer block rule created successfully.',
        ]);
    }

    public function show(Request $request, BlockedCustomer $blockedCustomer)
    {
        $this->adminOrEmployeeOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.blocked-customers.index');
        }

        $blockedCustomer->load([
            'sourceOrder:id,invoice_id,assigned_employee_id',
            'blockedBy:id,name,email',
            'unblockedBy:id,name,email',
        ]);

        return response()->json([
            'status' => true,
            'html'   => view('admin.blocked-customers.partials.show', compact('blockedCustomer'))->render(),
        ]);
    }

    public function edit(Request $request, BlockedCustomer $blockedCustomer)
    {
        $this->adminOrEmployeeOnly();

        if (! $request->ajax()) {
            return redirect()->route('admin.blocked-customers.index');
        }

        return response()->json([
            'status' => true,
            'html'   => view('admin.blocked-customers.partials.form', [
                'blockedCustomer' => $blockedCustomer,
                'isEdit'          => true,
                'action'          => route('admin.blocked-customers.update', $blockedCustomer->id),
            ])->render(),
        ]);
    }

    public function update(
        Request $request,
        BlockedCustomer $blockedCustomer,
        OrderBlockService $orderBlockService
    ) {
        $this->adminOrEmployeeOnly();

        $data = $this->validateBlockPayload($request, $orderBlockService);
        $orderBlockService->updateBlock($blockedCustomer, $data, auth()->user());

        return response()->json([
            'status'  => true,
            'message' => 'Customer block rule updated successfully.',
        ]);
    }

    public function toggleStatus(
        Request $request,
        BlockedCustomer $blockedCustomer,
        OrderBlockService $orderBlockService
    ) {
        $this->adminOrEmployeeOnly();

        $validated = $request->validate([
            'status' => ['required', 'boolean'],
        ]);

        $blockedCustomer = $orderBlockService->setStatus(
            $blockedCustomer,
            (bool) $validated['status'],
            auth()->user()
        );

        return response()->json([
            'status'  => true,
            'message' => $blockedCustomer->status
                ? 'Customer block rule activated successfully.'
                : 'Customer block rule unblocked successfully.',
        ]);
    }

    public function destroy(BlockedCustomer $blockedCustomer)
    {
        $this->adminOrEmployeeOnly();
        $blockedCustomer->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Customer block rule moved to trash successfully.',
        ]);
    }

    public function restore(int $id)
    {
        $this->adminOrEmployeeOnly();

        $blockedCustomer = BlockedCustomer::onlyTrashed()->findOrFail($id);
        $blockedCustomer->restore();
        $blockedCustomer->forceFill([
            'status'       => false,
            'unblocked_by' => auth()->id(),
            'unblocked_at' => now(),
        ])->save();

        return response()->json([
            'status'  => true,
            'message' => 'Block rule restored as inactive. Activate it after review.',
        ]);
    }

    public function forceDelete(int $id)
    {
        $this->adminOrEmployeeOnly();
        BlockedCustomer::onlyTrashed()->findOrFail($id)->forceDelete();

        return response()->json([
            'status'  => true,
            'message' => 'Customer block rule permanently deleted.',
        ]);
    }

    public function blockFromOrder(
        Request $request,
        Order $order,
        OrderBlockService $orderBlockService
    ) {
        $this->adminOrEmployeeOnly();
        $this->ensureEmployeeOrderAccess($order);

        $validated = $request->validate([
            'block_phone' => ['nullable', 'boolean'],
            'block_ip'    => ['nullable', 'boolean'],
            'reason'      => ['nullable', 'string', 'max:2000'],
        ]);

        $blockIp = (bool) ($validated['block_ip'] ?? false);
        $isFrontendOrder = (string) $order->created_via === Order::CREATED_VIA_FRONTEND;

        if ($blockIp && (! $isFrontendOrder || ! $orderBlockService->normalizeIp($order->source_ip))) {
            throw ValidationException::withMessages([
                'block_ip' => 'Customer IP blocking is available only for frontend orders with a captured customer IP.',
            ]);
        }

        $blockedCustomer = $orderBlockService->createBlock([
            'source_order_id' => $order->id,
            'customer_name'   => $order->customer_name,
            'phone'           => $order->phone,
            'ip_address'      => $order->source_ip,
            'block_phone'     => (bool) ($validated['block_phone'] ?? false),
            'block_ip'        => $blockIp,
            'reason'          => $validated['reason'] ?? null,
        ], auth()->user());

        return response()->json([
            'status'          => true,
            'message'         => 'Customer blocked successfully.',
            'blocked_rule_id' => $blockedCustomer->id,
        ]);
    }

    private function validateBlockPayload(
        Request $request,
        OrderBlockService $orderBlockService
    ): array {
        $request->merge([
            'phone'       => $orderBlockService->normalizePhone($request->input('phone')),
            'ip_address'  => $orderBlockService->normalizeIp($request->input('ip_address')),
            'block_phone' => $request->boolean('block_phone'),
            'block_ip'    => $request->boolean('block_ip'),
        ]);

        $validated = $request->validate([
            'source_order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'customer_name'   => ['nullable', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'ip_address'      => ['nullable', 'ip'],
            'block_phone'     => ['nullable', 'boolean'],
            'block_ip'        => ['nullable', 'boolean'],
            'reason'          => ['nullable', 'string', 'max:2000'],
        ]);

        if (! ($validated['block_phone'] ?? false) && ! ($validated['block_ip'] ?? false)) {
            throw ValidationException::withMessages([
                'block_phone' => 'Select at least one identifier: phone or IP address.',
            ]);
        }

        return $validated;
    }
}
