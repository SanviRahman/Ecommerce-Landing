<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light small text-uppercase font-weight-bold text-muted">
            <tr>
                <th width="40" class="text-center px-4">
                    <input type="checkbox" id="check_all" class="shadow-none cursor-pointer">
                </th>
                <th>Request ID & Date</th>
                <th>Customer Info</th>
                <th>Product Info</th>
                <th>Status</th>
                <th width="120" class="text-right px-4">Actions</th>
            </tr>
        </thead>

        <tbody>
            @forelse($bulkOrders as $order)
                <tr class="{{ isset($isTrash) && $isTrash ? 'bg-light-red' : '' }}">
                    <td class="text-center px-4">
                        <input type="checkbox" class="row-checkbox shadow-none cursor-pointer" value="{{ $order->id }}">
                    </td>

                    <td>
                        <div class="font-weight-bold text-dark">
                            #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}
                        </div>
                        <div class="small text-muted">
                            {{ $order->created_at->format('d M, Y h:i A') }}
                        </div>
                    </td>

                    <td>
                        <div class="font-weight-bold text-dark">
                            {{ $order->customer_name }}
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-phone-alt mr-1"></i> {{ $order->phone }}
                        </div>
                        @if($order->company_name)
                            <div class="small text-muted mt-1">
                                <i class="far fa-building mr-1"></i> {{ $order->company_name }}
                            </div>
                        @endif
                    </td>

                    <td>
                        <div class="font-weight-bold text-primary">
                            {{ $order->product_name ?? 'Not Specified' }}
                        </div>
                        <div class="small font-weight-bold mt-1">
                            Expected Qty: <span class="badge badge-info">{{ $order->expected_quantity }}</span>
                        </div>
                    </td>

                    <td>
                        @if(isset($isTrash) && $isTrash)
                            <span class="badge badge-danger px-3 py-1 shadow-xs">Deleted</span>
                        @else
                            @php
                                $badgeClass = 'badge-secondary';
                                if($order->status === 'new') $badgeClass = 'badge-info';
                                elseif($order->status === 'contacted') $badgeClass = 'badge-primary';
                                elseif($order->status === 'quoted') $badgeClass = 'badge-warning';
                                elseif($order->status === 'confirmed') $badgeClass = 'badge-success';
                                elseif($order->status === 'cancelled') $badgeClass = 'badge-danger';
                            @endphp
                            <span class="badge {{ $badgeClass }} px-3 py-1 shadow-xs text-uppercase">
                                {{ $order->status }}
                            </span>
                        @endif
                    </td>

                    <td class="text-right px-4">
                        <div class="btn-group shadow-sm rounded border bg-white overflow-hidden">
                            @if(isset($isTrash) && $isTrash)
                                <button type="button" class="btn btn-sm btn-white text-success btnRestore"
                                    data-url="{{ route('admin.bulk-orders.restore', $order->id) }}" title="Restore">
                                    <i class="fas fa-trash-restore"></i>
                                </button>

                                <button type="button" class="btn btn-sm btn-white text-danger btnForceDelete"
                                    data-url="{{ route('admin.bulk-orders.force_delete', $order->id) }}" title="Delete Forever">
                                    <i class="fas fa-skull-crossbones"></i>
                                </button>
                            @else
                                <a href="{{ route('admin.bulk-orders.show', $order->id) }}" class="btn btn-sm btn-white text-info" title="View & Manage">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <button type="button" class="btn btn-sm btn-white text-danger btnDelete"
                                    data-url="{{ route('admin.bulk-orders.destroy', $order->id) }}" title="Move to Trash">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="py-4">
                            <i class="fas fa-boxes fa-3x text-light mb-3"></i>
                            <h6 class="text-muted">No bulk orders found matching your criteria.</h6>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($bulkOrders->hasPages())
    <div class="px-4 py-3 border-top bg-white d-flex justify-content-center">
        {!! $bulkOrders->appends(request()->all())->links('pagination::bootstrap-4') !!}
    </div>
@endif