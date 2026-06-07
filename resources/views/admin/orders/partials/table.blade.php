@php
    $canBulkManageOrders = auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isEmployee());
    $canDeleteOrders = auth()->check() && auth()->user()->isAdmin();
@endphp
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 order-index-table">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                @if($canBulkManageOrders)
                    <th width="40" class="text-center px-4">
                        <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                    </th>
                @endif

                <th>Order Info</th>
                <th>Customer</th>
                <th>Products</th>
                <th>Amount</th>
                <th>Courier</th>
                <th>Date</th>
                <th>Status</th>
                <th>Admin Note</th>
                <th width="130" class="text-center">Actions</th>
                <th>Employee</th>
                <th>Payment</th>
            </tr>
        </thead>

        <tbody>
            @forelse($orders as $order)
                @php
                    $firstProductImage = $order->first_product_image_url ?? null;
                    $orderCreatedAt = method_exists($order, 'localDateTime')
                        ? $order->localDateTime('created_at')
                        : ($order->created_at ? $order->created_at->copy()->timezone('Asia/Dhaka') : null);
                @endphp

                <tr class="{{ !empty($isTrash) ? 'bg-light-red' : '' }}">
                    @if($canBulkManageOrders)
                        <td class="text-center px-4">
                            <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $order->id }}">
                        </td>
                    @endif

                    {{-- Order Info --}}
                    <td>
                        <div class="font-weight-bold text-dark">
                            #{{ $order->invoice_id }}
                        </div>

                        @if(empty($isTrash))
                            <button type="button"
                                    class="btn btn-xs btn-outline-primary btnFraudCheck mt-1"
                                    data-url="{{ route('admin.orders.fraud_check', $order->id) }}"
                                    data-phone="{{ $order->phone }}"
                                    data-customer="{{ $order->customer_name }}"
                                    data-invoice="{{ $order->invoice_id }}"
                                    title="Fraud Check">
                                <i class="fas fa-user-shield mr-1"></i>
                                Check
                            </button>
                        @endif

                        <div class="small text-muted mt-1">
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

                        @if($order->orderField)
                            <span class="badge mt-1 text-white" style="background: {{ $order->orderField->color ?: '#2563eb' }};">
                                {{ $order->orderField->name }}
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

                    {{-- Amount + First Product Image --}}
                    <td>
                        <div class="font-weight-bold">
                            ৳{{ number_format($order->total_amount ?? 0) }}
                        </div>

                        <small class="text-muted d-block">
                            Sub: ৳{{ number_format($order->sub_total ?? 0) }}
                        </small>

                        @if($firstProductImage)
                            <img src="{{ $firstProductImage }}"
                                 alt="{{ $order->items->first()->product_name ?? 'Product' }}"
                                 class="order-first-product-img mt-2">
                        @else
                            <div class="order-first-product-img-placeholder mt-2">
                                <i class="fas fa-image"></i>
                            </div>
                        @endif
                    </td>

                    {{-- Courier --}}
                    <td>
                        @if($order->courier)
                            <span class="badge badge-info">{{ $order->courier->name }}</span>
                        @elseif($order->courier_service)
                            <span class="badge badge-info">
                                {{ $courierServices[$order->courier_service] ?? ucwords(str_replace('_', ' ', $order->courier_service)) }}
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
                                        SF: {{ ucwords(str_replace('_', ' ', $order->steadfast_status)) }}
                                    </small>
                                @endif
                            </div>
                        @endif

                        @if($order->courier_service === 'pathao')
                            <div class="mt-1 sf-courier-box">
                                @if($order->pathao_consignment_id)
                                    <small class="d-block text-success font-weight-bold">
                                        <i class="fas fa-barcode mr-1"></i>
                                        CID: {{ $order->pathao_consignment_id }}
                                    </small>
                                @else
                                    <small class="d-block text-warning font-weight-bold">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        Not sent yet
                                    </small>
                                @endif

                                @if($order->pathao_status)
                                    <small class="d-block text-muted">
                                        Pathao: {{ ucwords(str_replace('_', ' ', $order->pathao_status)) }}
                                    </small>
                                @endif
                            </div>
                        @endif
                    </td>

                    {{-- Date --}}
                    <td>
                        <div class="small">
                            {{ $orderCreatedAt ? $orderCreatedAt->format('d M Y') : '-' }}
                        </div>

                        <small class="text-muted">
                            {{ $orderCreatedAt ? $orderCreatedAt->format('h:i A') : '' }}
                        </small>
                    </td>

                    {{-- Status --}}
                    <td>
                        @if($order->order_status === 'pending')
                            <span class="badge badge-warning">Pending</span>
                        @elseif($order->order_status === 'confirmed')
                            <span class="badge badge-primary">Confirmed</span>
                        @elseif($order->order_status === 'processing')
                            <span class="badge badge-primary order-processing-badge">Processing</span>
                        @elseif($order->order_status === 'shipped')
                            <span class="badge badge-info">Shipped</span>
                        @elseif($order->order_status === 'delivered')
                            <span class="badge badge-success">Delivered</span>
                        @elseif($order->order_status === 'cancelled')
                            <span class="badge badge-danger">Cancelled</span>
                        @elseif($order->order_status === 'fake')
                            <span class="badge badge-dark">Fake</span>
                        @elseif($order->order_status === 'stock_out')
                            <span class="badge badge-secondary">Stock Out</span>
                        @else
                            <span class="badge badge-light border">
                                {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                            </span>
                        @endif

                        @if($order->is_fake)
                            <div>
                                <span class="badge badge-danger mt-1">Fake Order</span>
                            </div>
                        @endif
                    </td>

                    {{-- Admin Note --}}
                    <td style="min-width: 240px;">
                        @if($canBulkManageOrders && empty($isTrash))
                            <textarea class="form-control form-control-sm admin-note-input"
                                      rows="2"
                                      data-url="{{ route('admin.orders.update_admin_note', $order->id) }}"
                                      data-order-id="{{ $order->id }}"
                                      data-original="{{ e($order->admin_note ?? '') }}"
                                      placeholder="Write admin note...">{{ $order->admin_note }}</textarea>

                            <div class="admin-note-status small text-muted mt-1" data-order-id="{{ $order->id }}">
                                Auto save enabled
                            </div>
                        @else
                            <span class="small text-muted">{{ $order->admin_note ?: '-' }}</span>
                        @endif
                    </td>

                    {{-- Actions --}}
                    <td class="text-center">
                        <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                            @if(!empty($isTrash))
                                @if($canDeleteOrders)
                                    <button type="button" class="btn btn-sm btn-white text-success btnRestore"
                                            data-url="{{ route('admin.orders.restore', $order->id) }}" title="Restore">
                                        <i class="fas fa-trash-restore"></i>
                                    </button>
                                @endif
                            @else
                                <a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-sm btn-white text-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>

                                @if($canBulkManageOrders)
                                    <a href="{{ route('admin.orders.edit', $order->id) }}" class="btn btn-sm btn-white text-primary" title="Edit Order">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                @endif

                                <a href="{{ route('admin.orders.invoice', $order->id) }}" class="btn btn-sm btn-white text-secondary" title="Invoice Print">
                                    <i class="fas fa-file-invoice"></i>
                                </a>

                                {{-- PDF download option removed from index action column as requested --}}

                                @if($canBulkManageOrders && $order->courier_service === 'steadfast')
                                    @if(empty($order->steadfast_consignment_id))
                                        <button type="button" class="btn btn-sm btn-white text-primary btnSendSteadfast"
                                                data-url="{{ route('admin.orders.send_steadfast', $order->id) }}" title="Send to SteadFast">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-white text-warning btnSyncSteadfast"
                                                data-url="{{ route('admin.orders.sync_steadfast_status', $order->id) }}" title="Sync SteadFast Status">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    @endif
                                @endif

                                @if($canBulkManageOrders && $order->courier_service === 'pathao')
                                    <button type="button" class="btn btn-sm btn-white text-success btnSendPathao"
                                            data-url="{{ route('admin.orders.send_pathao', $order->id) }}" title="Send to Pathao">
                                        <i class="fas fa-shipping-fast"></i>
                                    </button>
                                @endif

                                @if($canDeleteOrders)
                                    <button type="button" class="btn btn-sm btn-white text-danger btnDelete"
                                            data-url="{{ route('admin.orders.destroy', $order->id) }}" title="Move to Trash">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                @endif
                            @endif
                        </div>
                    </td>

                    {{-- Employee --}}
                    <td>
                        @if($order->assignedEmployee)
                            <div class="font-weight-bold">{{ $order->assignedEmployee->name }}</div>
                            <small class="text-muted">{{ $order->assignedEmployee->email }}</small>
                        @else
                            <span class="badge badge-light border">Unassigned</span>
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
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $canBulkManageOrders ? 12 : 11 }}" class="text-center text-muted py-5">
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
.bg-light-red { background-color: #fffafa; }
.shadow-xs { box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); }
.cursor-pointer { cursor: pointer; }
.btn-white { background: #fff; border: none; transition: 0.2s; }
.btn-white:hover { background: #f8f9fa; transform: translateY(-1px); }
.btn-xs { padding: 2px 7px; font-size: 11px; line-height: 1.4; border-radius: 4px; }
.pagination { margin-bottom: 0; }
.page-item.active .page-link { background-color: #007bff; border-color: #007bff; }
.page-link { color: #6c757d; border-radius: 5px !important; margin: 0 2px; }
.admin-note-input { min-width: 210px; font-size: 12px; resize: vertical; }
.admin-note-status { font-size: 11px; min-height: 15px; }
.admin-note-status.saving { color: #2563eb !important; }
.admin-note-status.saved { color: #16a34a !important; }
.admin-note-status.error { color: #dc2626 !important; }
.sf-courier-box { line-height: 1.3; }
.sf-courier-box small { font-size: 11px; }
.btn-group .btn { border-radius: 0 !important; }
.order-index-table th,
.order-index-table td { vertical-align: middle !important; }
.order-first-product-img {
    width: 58px;
    height: 58px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
}
.order-first-product-img-placeholder {
    width: 58px;
    height: 58px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
}
.order-processing-badge {
    background: #ec00ff !important;
    color: #ffffff;
}
</style>
