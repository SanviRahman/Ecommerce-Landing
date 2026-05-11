<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                @if(auth()->user()->isAdmin())
                <th width="40" class="text-center px-4">
                    <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                </th>
                @endif

                <th>Order Info</th>
                <th>Customer</th>
                <th>Products</th>
                <th>Amount</th>
                <th>Courier</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Admin Note</th>
                <th>Employee</th>
                <th>Date</th>
                <th width="190" class="text-right px-4">Actions</th>
            </tr>
        </thead>

        <tbody>
            @forelse($orders as $order)
            <tr class="{{ !empty($isTrash) ? 'bg-light-red' : '' }}">
                @if(auth()->user()->isAdmin())
                <td class="text-center px-4">
                    <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $order->id }}">
                </td>
                @endif

                {{-- Order Info --}}
                <td>
                    <div class="font-weight-bold text-dark">
                        #{{ $order->invoice_id }}
                    </div>

                    <div class="small text-muted">
                        Source:
                        <span title="{{ $order->source_url }}">
                            {{ $order->source_url ? \Illuminate\Support\Str::limit($order->source_url, 28) : '-' }}
                        </span>
                    </div>

                    @if($order->campaign)
                    <span class="badge badge-light border mt-1">
                        {{ \Illuminate\Support\Str::limit($order->campaign->title, 22) }}
                    </span>
                    @endif
                </td>

                {{-- Customer --}}
                <td>
                    <div class="font-weight-bold">{{ $order->customer_name }}</div>
                    <div class="small">{{ $order->phone }}</div>

                    <div class="small text-muted" title="{{ $order->address }}">
                        {{ \Illuminate\Support\Str::limit($order->address, 35) }}
                    </div>

                    @if($order->delivery_area)
                    <span class="badge badge-light border">
                        {{ ucwords(str_replace('_', ' ', $order->delivery_area)) }}
                    </span>
                    @endif
                </td>

                {{-- Products --}}
                <td>
                    @forelse($order->items as $item)
                    <div class="small">
                        {{ $item->quantity }} x {{ \Illuminate\Support\Str::limit($item->product_name, 28) }}
                    </div>
                    @empty
                    <span class="text-muted small">No items</span>
                    @endforelse
                </td>

                {{-- Amount --}}
                <td>
                    <div class="font-weight-bold">
                        ৳{{ number_format($order->total_amount ?? 0) }}
                    </div>

                    <small class="text-muted">
                        Sub: ৳{{ number_format($order->sub_total ?? 0) }}
                    </small>
                </td>

                {{-- Courier --}}
                <td>
                    @if($order->courier_service)
                    <span class="badge badge-info">
                        {{ $courierServices[$order->courier_service] ?? $order->courier_service }}
                    </span>
                    @else
                    <span class="badge badge-light border">Not selected</span>
                    @endif

                    @if($order->courier_service === 'steadfast')
                    <div class="mt-1 sf-courier-box">
                        @if($order->steadfast_tracking_code)
                        <small class="d-block text-success font-weight-bold">
                            <i class="fas fa-barcode mr-1"></i>
                            Tracking: {{ $order->steadfast_tracking_code }}
                        </small>
                        @else
                        <small class="d-block text-warning font-weight-bold">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            Not sent yet
                        </small>
                        @endif

                        @if($order->steadfast_status)
                        <small class="d-block text-muted">
                            <i class="fas fa-truck-loading mr-1"></i>
                            SF Status:
                            {{ ucwords(str_replace('_', ' ', $order->steadfast_status)) }}
                        </small>
                        @endif

                        @if($order->steadfast_consignment_id)
                        <small class="d-block text-muted">
                            CID: {{ $order->steadfast_consignment_id }}
                        </small>
                        @endif

                        @if($order->steadfast_synced_at)
                        <small class="d-block text-muted">
                            Sync:
                            {{ $order->steadfast_synced_at->format('d M, h:i A') }}
                        </small>
                        @endif
                    </div>
                    @endif
                </td>

                {{-- Status --}}
                <td>
                    @if($order->order_status === 'pending')
                    <span class="badge badge-warning">Pending</span>
                    @elseif($order->order_status === 'confirmed')
                    <span class="badge badge-primary">Confirmed</span>
                    @elseif($order->order_status === 'processing')
                    <span class="badge badge-secondary">Processing</span>
                    @elseif($order->order_status === 'shipped')
                    <span class="badge badge-info">Shipped</span>
                    @elseif($order->order_status === 'delivered')
                    <span class="badge badge-success">Delivered</span>
                    @elseif($order->order_status === 'cancelled')
                    <span class="badge badge-danger">Cancelled</span>
                    @elseif($order->order_status === 'fake')
                    <span class="badge badge-danger">Fake</span>
                    @else
                    <span class="badge badge-light border">
                        {{ ucfirst($order->order_status) }}
                    </span>
                    @endif

                    @if($order->is_fake)
                    <div>
                        <span class="badge badge-danger mt-1">Fake Order</span>
                    </div>
                    @endif
                </td>

                {{-- Payment --}}
                <td>
                    @if($order->payment_status === 'cod_pending')
                    <span class="badge badge-warning">COD Pending</span>
                    @elseif($order->payment_status === 'collected')
                    <span class="badge badge-success">Collected</span>
                    @elseif($order->payment_status === 'failed')
                    <span class="badge badge-danger">Failed</span>
                    @elseif($order->payment_status === 'unpaid')
                    <span class="badge badge-secondary">Unpaid</span>
                    @else
                    <span class="badge badge-light border">
                        {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                    </span>
                    @endif
                </td>

                {{-- Admin Note Auto Save --}}
                <td style="min-width: 240px;">
                    @if(auth()->user()->isAdmin() && empty($isTrash))
                    <textarea class="form-control form-control-sm admin-note-input" rows="2"
                        data-url="{{ route('admin.orders.update_admin_note', $order->id) }}"
                        data-order-id="{{ $order->id }}" data-original="{{ e($order->admin_note ?? '') }}"
                        placeholder="Write admin note...">{{ $order->admin_note }}</textarea>

                    <div class="admin-note-status small text-muted mt-1" data-order-id="{{ $order->id }}">
                        Auto save enabled
                    </div>
                    @else
                    <span class="small text-muted">
                        {{ $order->admin_note ?: '-' }}
                    </span>
                    @endif
                </td>

                {{-- Employee --}}
                <td>
                    @if($order->assignedEmployee)
                    <div class="font-weight-bold">
                        {{ $order->assignedEmployee->name }}
                    </div>

                    <small class="text-muted">
                        {{ $order->assignedEmployee->email }}
                    </small>
                    @else
                    <span class="badge badge-light border">Unassigned</span>
                    @endif
                </td>

                {{-- Date --}}
                <td>
                    <div class="small">
                        {{ $order->created_at ? $order->created_at->format('d M Y') : '-' }}
                    </div>

                    <small class="text-muted">
                        {{ $order->created_at ? $order->created_at->format('h:i A') : '' }}
                    </small>
                </td>

                {{-- Actions --}}
                <td class="text-right px-4">
                    <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                        @if(!empty($isTrash))
                        @if(auth()->user()->isAdmin())
                        <button type="button" class="btn btn-sm btn-white text-success btnRestore"
                            data-url="{{ route('admin.orders.restore', $order->id) }}" title="Restore">
                            <i class="fas fa-trash-restore"></i>
                        </button>

                        <button type="button" class="btn btn-sm btn-white text-danger btnForceDelete"
                            data-url="{{ route('admin.orders.force_delete', $order->id) }}" title="Delete Forever">
                            <i class="fas fa-skull-crossbones"></i>
                        </button>
                        @endif
                        @else
                        <a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-sm btn-white text-info"
                            title="View">
                            <i class="fas fa-eye"></i>
                        </a>

                        <a href="{{ route('admin.orders.invoice', $order->id) }}"
                            class="btn btn-sm btn-white text-secondary" title="Invoice Print">
                            <i class="fas fa-file-invoice"></i>
                        </a>

                        <a href="{{ route('admin.orders.invoice.download', $order->id) }}"
                            class="btn btn-sm btn-white text-success" title="Download Invoice PDF">
                            <i class="fas fa-file-download"></i>
                        </a>

                        @if(auth()->user()->isAdmin() && $order->courier_service === 'steadfast')
                        @if(empty($order->steadfast_consignment_id))
                        <button type="button" class="btn btn-sm btn-white text-primary btnSendSteadfast"
                            data-url="{{ route('admin.orders.send_steadfast', $order->id) }}" title="Send to SteadFast">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                        @else
                        <button type="button" class="btn btn-sm btn-white text-warning btnSyncSteadfast"
                            data-url="{{ route('admin.orders.sync_steadfast_status', $order->id) }}"
                            title="Sync SteadFast Status">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        @endif
                        @endif

                        @if(auth()->user()->isAdmin() && $order->courier_service === 'pathao')
                        <button type="button" class="btn btn-sm btn-white text-success btnSendPathao"
                            data-url="{{ route('admin.orders.send_pathao', $order->id) }}" title="Send to Pathao">
                            <i class="fas fa-shipping-fast"></i>
                        </button>
                        @endif

                        @if(auth()->user()->isAdmin())
                        <button type="button" class="btn btn-sm btn-white text-danger btnDelete"
                            data-url="{{ route('admin.orders.destroy', $order->id) }}" title="Move to Trash">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        @endif
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ auth()->user()->isAdmin() ? 12 : 11 }}" class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <div>No orders found.</div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($orders->hasPages())
<div class="px-4 py-3 border-top bg-white">
    {{ $orders->withQueryString()->links() }}
</div>
@endif

<style>
.bg-light-red {
    background-color: #fffafa;
}

.shadow-xs {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.cursor-pointer {
    cursor: pointer;
}

.btn-white {
    background: #fff;
    border: none;
    transition: 0.2s;
}

.btn-white:hover {
    background: #f8f9fa;
    transform: translateY(-1px);
}

.pagination {
    margin-bottom: 0;
}

.page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}

.page-link {
    color: #6c757d;
    border-radius: 5px !important;
    margin: 0 2px;
}

.admin-note-input {
    min-width: 210px;
    font-size: 12px;
    resize: vertical;
}

.admin-note-status {
    font-size: 11px;
    min-height: 15px;
}

.admin-note-status.saving {
    color: #2563eb !important;
}

.admin-note-status.saved {
    color: #16a34a !important;
}

.admin-note-status.error {
    color: #dc2626 !important;
}

.sf-courier-box {
    line-height: 1.3;
}

.sf-courier-box small {
    font-size: 11px;
}

.btn-group .btn {
    border-radius: 0 !important;
}
</style>