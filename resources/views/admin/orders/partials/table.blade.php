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
            <th>Amount</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Employee</th>
            <th>Date</th>
            <th width="160" class="text-right px-4">Actions</th>
        </tr>
        </thead>

        <tbody>
        @forelse($orders as $order)
            <tr class="{{ isset($isTrash) && $isTrash ? 'bg-light-red' : '' }}">
                @if(auth()->user()->isAdmin())
                    <td class="text-center px-4">
                        <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $order->id }}">
                    </td>
                @endif

                <td>
                    <div class="font-weight-bold text-dark">
                        #{{ $order->invoice_id }}
                    </div>

                    <div class="small text-muted">
                        Source:
                        <span title="{{ $order->source_url }}">
                            {{ $order->source_url ? Str::limit($order->source_url, 35) : '-' }}
                        </span>
                    </div>

                    @if($order->is_fake || $order->order_status === 'fake')
                        <span class="badge badge-danger mt-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Fake
                        </span>
                    @endif
                </td>

                <td>
                    <div class="font-weight-bold text-dark">
                        {{ $order->customer_name }}
                    </div>

                    <div class="small text-muted">
                        <i class="fas fa-phone-alt mr-1"></i> {{ $order->phone }}
                    </div>

                    <div class="small text-muted" title="{{ $order->address }}">
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        {{ Str::limit($order->address, 35) }}
                    </div>
                </td>

                <td>
                    <div class="font-weight-bold">
                        {{ number_format($order->total_amount ?? 0, 2) }}
                    </div>

                    <div class="small text-muted">
                        Sub: {{ number_format($order->sub_total ?? 0, 2) }}
                    </div>

                    <div class="small text-muted">
                        Delivery: {{ number_format($order->shipping_charge ?? 0, 2) }}
                    </div>
                </td>

                <td>
                    @php
                        $statusClass = match($order->order_status) {
                            'pending' => 'badge-warning',
                            'confirmed' => 'badge-primary',
                            'processing' => 'badge-info',
                            'shipped' => 'badge-secondary',
                            'delivered' => 'badge-success',
                            'cancelled' => 'badge-danger',
                            'fake' => 'badge-danger',
                            default => 'badge-light',
                        };
                    @endphp

                    <span class="badge {{ $statusClass }} px-3">
                        {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                    </span>
                </td>

                <td>
                    @php
                        $paymentClass = match($order->payment_status) {
                            'unpaid' => 'badge-warning',
                            'cod_pending' => 'badge-info',
                            'collected' => 'badge-success',
                            'failed' => 'badge-danger',
                            default => 'badge-light',
                        };
                    @endphp

                    <span class="badge {{ $paymentClass }} px-3">
                        {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                    </span>
                </td>

                <td>
                    @if($order->assignedEmployee)
                        <div class="font-weight-bold small text-dark">
                            {{ $order->assignedEmployee->name }}
                        </div>

                        <div class="small text-muted">
                            {{ $order->assignedEmployee->email }}
                        </div>
                    @else
                        <span class="badge badge-light border text-muted">
                            Unassigned
                        </span>
                    @endif
                </td>

                <td>
                    <div class="small font-weight-bold">
                        {{ $order->created_at ? $order->created_at->format('d M, Y') : '-' }}
                    </div>

                    <div class="small text-muted">
                        {{ $order->created_at ? $order->created_at->format('h:i A') : '-' }}
                    </div>
                </td>

                <td class="text-right px-4">
                    <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                        @if(isset($isTrash) && $isTrash)
                            @if(auth()->user()->isAdmin())
                                <button type="button"
                                        class="btn btn-sm btn-white text-success btnRestore"
                                        data-url="{{ route('admin.orders.restore', $order->id) }}"
                                        title="Restore">
                                    <i class="fas fa-trash-restore"></i>
                                </button>

                                <button type="button"
                                        class="btn btn-sm btn-white text-danger btnForceDelete"
                                        data-url="{{ route('admin.orders.force_delete', $order->id) }}"
                                        title="Delete Forever">
                                    <i class="fas fa-skull-crossbones"></i>
                                </button>
                            @endif
                        @else
                            <a href="{{ route('admin.orders.show', $order->id) }}"
                               class="btn btn-sm btn-white text-info"
                               title="View">
                                <i class="fas fa-eye"></i>
                            </a>

                            <a href="{{ route('admin.orders.invoice', $order->id) }}"
                               class="btn btn-sm btn-white text-secondary"
                               title="Invoice">
                                <i class="fas fa-file-invoice"></i>
                            </a>

                            @if(auth()->user()->isAdmin())
                                <button type="button"
                                        class="btn btn-sm btn-white text-danger btnDelete"
                                        data-url="{{ route('admin.orders.destroy', $order->id) }}"
                                        title="Move to Trash">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endif
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ auth()->user()->isAdmin() ? 9 : 8 }}" class="text-center py-5">
                    <div class="py-4">
                        <i class="fas fa-shopping-cart fa-3x text-light mb-3"></i>
                        <h6 class="text-muted">No orders found matching your criteria.</h6>
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($orders->hasPages())
    <div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
        {!! $orders->appends(request()->all())->links('pagination::bootstrap-4') !!}
    </div>
@endif

<style>
    .bg-light-red {
        background-color: #fffafa;
    }

    .shadow-xs {
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
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
</style>