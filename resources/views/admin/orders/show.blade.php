@extends('adminlte::page')

@section('title', $title ?? 'Order Details')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="mb-0">Order Details</h1>

        <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i>
            Back
        </a>
    </div>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0">
                    <i class="fas fa-receipt mr-1"></i>
                    Order Information
                </h3>
            </div>

            <div class="card-body">
                <table class="table table-bordered">
                    <tr>
                        <th width="220">Invoice ID</th>
                        <td>
                            <strong>{{ $order->invoice_id }}</strong>
                        </td>
                    </tr>

                    <tr>
                        <th>Customer Name</th>
                        <td>{{ $order->customer_name }}</td>
                    </tr>

                    <tr>
                        <th>Phone</th>
                        <td>
                            <a href="tel:{{ $order->phone }}">{{ $order->phone }}</a>
                        </td>
                    </tr>

                    <tr>
                        <th>Address</th>
                        <td>{{ $order->address }}</td>
                    </tr>

                    <tr>
                        <th>Delivery Area</th>
                        <td>{{ ucwords(str_replace('_', ' ', $order->delivery_area ?? 'N/A')) }}</td>
                    </tr>

                    <tr>
                        <th>Courier</th>
                        <td>
                            @if($order->courier)
                                <span class="badge badge-info">
                                    {{ $order->courier->name }}
                                </span>
                            @else
                                <span class="badge badge-light border">
                                    No Courier
                                </span>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <th>Assigned Employee</th>
                        <td>{{ $order->assignedEmployee->name ?? 'Not Assigned' }}</td>
                    </tr>

                    <tr>
                        <th>Order Status</th>
                        <td>
                            <span class="badge badge-primary">
                                {{ ucfirst(str_replace('_', ' ', $order->order_status)) }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <th>Payment Status</th>
                        <td>
                            <span class="badge badge-warning">
                                {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <th>Free Delivery</th>
                        <td>
                            @if($order->is_free_delivery)
                                <span class="badge badge-success">Yes</span>
                            @else
                                <span class="badge badge-secondary">No</span>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <th>Customer Note</th>
                        <td>{{ $order->customer_note ?: '-' }}</td>
                    </tr>

                    <tr>
                        <th>Admin Note</th>
                        <td>{{ $order->admin_note ?: '-' }}</td>
                    </tr>

                    <tr>
                        <th>Created At</th>
                        <td>{{ $order->created_at?->format('d M Y h:i A') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0">
                    <i class="fas fa-box mr-1"></i>
                    Ordered Products
                </h3>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th width="120">Unit Price</th>
                            <th width="100">Qty</th>
                            <th width="140">Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>{{ $item->product_name }}</td>
                                <td>৳{{ number_format($item->unit_price) }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>৳{{ number_format($item->total_price) }}</td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">Subtotal</th>
                            <th>৳{{ number_format($order->sub_total) }}</th>
                        </tr>

                        <tr>
                            <th colspan="3" class="text-right">Delivery Charge</th>
                            <th>
                                @if($order->is_free_delivery)
                                    <span class="text-success">Free Delivery</span>
                                @else
                                    ৳{{ number_format($order->shipping_charge) }}
                                @endif
                            </th>
                        </tr>

                        <tr>
                            <th colspan="3" class="text-right">COD Charge</th>
                            <th>৳{{ number_format($order->cod_charge) }}</th>
                        </tr>

                        <tr>
                            <th colspan="3" class="text-right">Grand Total</th>
                            <th class="text-success">৳{{ number_format($order->total_amount) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if($order->steadfast_consignment_id || $order->steadfast_tracking_code || $order->steadfast_status)
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-truck mr-1"></i>
                        SteadFast Information
                    </h3>
                </div>

                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="220">Consignment ID</th>
                            <td>{{ $order->steadfast_consignment_id ?: '-' }}</td>
                        </tr>

                        <tr>
                            <th>Tracking Code</th>
                            <td>{{ $order->steadfast_tracking_code ?: '-' }}</td>
                        </tr>

                        <tr>
                            <th>Status</th>
                            <td>{{ $order->steadfast_status ?: '-' }}</td>
                        </tr>

                        <tr>
                            <th>Note</th>
                            <td>{{ $order->steadfast_note ?: '-' }}</td>
                        </tr>

                        <tr>
                            <th>Sent At</th>
                            <td>{{ $order->steadfast_sent_at?->format('d M Y h:i A') ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        @endif

        @if($order->pathao_consignment_id || $order->pathao_merchant_order_id || $order->pathao_status)
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-shipping-fast mr-1"></i>
                        Pathao Information
                    </h3>
                </div>

                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="220">Consignment ID</th>
                            <td>{{ $order->pathao_consignment_id ?: '-' }}</td>
                        </tr>

                        <tr>
                            <th>Merchant Order ID</th>
                            <td>{{ $order->pathao_merchant_order_id ?: '-' }}</td>
                        </tr>

                        <tr>
                            <th>Status</th>
                            <td>{{ $order->pathao_status ?: '-' }}</td>
                        </tr>

                        <tr>
                            <th>Delivery Fee</th>
                            <td>৳{{ number_format($order->pathao_delivery_fee ?? 0) }}</td>
                        </tr>

                        <tr>
                            <th>Note</th>
                            <td>{{ $order->pathao_note ?: '-' }}</td>
                        </tr>

                        <tr>
                            <th>Sent At</th>
                            <td>{{ $order->pathao_sent_at?->format('d M Y h:i A') ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0">
                    <i class="fas fa-truck mr-1"></i>
                    Courier Select
                </h3>
            </div>

            <div class="card-body">
                <form id="orderCourierUpdateForm"
                      data-url="{{ route('admin.orders.update_courier', $order->id) }}">
                    @csrf
                    @method('PATCH')

                    <div class="form-group">
                        <label>Courier</label>

                        <select name="courier_id" class="form-control">
                            <option value="">No Courier</option>

                            @foreach($couriers ?? [] as $courier)
                                <option value="{{ $courier->id }}"
                                    @selected((int) $order->courier_id === (int) $courier->id)>
                                    {{ $courier->name }}
                                </option>
                            @endforeach
                        </select>

                        <small class="text-muted">
                            এই courier list Add Courier menu থেকে আসবে।
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save mr-1"></i>
                        Update Courier
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0">
                    <i class="fas fa-cogs mr-1"></i>
                    Quick Actions
                </h3>
            </div>

            <div class="card-body">
                <a href="{{ route('admin.orders.invoice', $order->id) }}"
                   target="_blank"
                   class="btn btn-secondary btn-block">
                    <i class="fas fa-file-invoice mr-1"></i>
                    View Invoice
                </a>

                <a href="{{ route('admin.orders.invoice.download', $order->id) }}"
                   class="btn btn-info btn-block">
                    <i class="fas fa-download mr-1"></i>
                    Download Invoice
                </a>

                @if(auth()->user()->isAdmin())
                    @if($order->courier_service === 'pathao')
                        <button type="button"
                                class="btn btn-success btn-block btnSendPathao"
                                data-url="{{ route('admin.orders.send_pathao', $order->id) }}">
                            <i class="fas fa-shipping-fast mr-1"></i>
                            Send Pathao
                        </button>
                    @else
                        <button type="button"
                                class="btn btn-success btn-block btnSendSteadfast"
                                data-url="{{ route('admin.orders.send_steadfast', $order->id) }}">
                            <i class="fas fa-truck mr-1"></i>
                            Send SteadFast
                        </button>

                        <button type="button"
                                class="btn btn-warning btn-block btnSyncSteadfast"
                                data-url="{{ route('admin.orders.sync_steadfast_status', $order->id) }}">
                            <i class="fas fa-sync mr-1"></i>
                            Sync SteadFast Status
                        </button>
                    @endif
                @endif
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0">
                    <i class="fas fa-edit mr-1"></i>
                    Update Status
                </h3>
            </div>

            <div class="card-body">
                <form id="orderStatusForm" data-url="{{ route('admin.orders.update_status', $order->id) }}">
                    @csrf
                    @method('PATCH')

                    <div class="form-group">
                        <label>Order Status</label>

                        <select name="order_status" class="form-control">
                            @foreach([
                                'pending',
                                'confirmed',
                                'processing',
                                'shipped',
                                'delivered',
                                'cancelled',
                                'fake',
                            ] as $status)
                                <option value="{{ $status }}" @selected($order->order_status === $status)>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="note" rows="2" class="form-control"></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-block">
                        Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('plugins.Sweetalert2', true)

@section('js')
<script>
$(document).ready(function () {
    function swalConfirmed(result) {
        return result.isConfirmed || result.value;
    }

    function showToast(type, message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: type,
                type: type,
                title: message,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2200,
                timerProgressBar: true,
                toast: true
            });
        } else {
            alert(message);
        }
    }

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    });

    $(document).on('submit', '#orderCourierUpdateForm', function (e) {
        e.preventDefault();

        let form = $(this);
        let button = form.find('button[type="submit"]');
        let oldButtonHtml = button.html();

        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Updating...');

        $.ajax({
            url: form.data('url'),
            type: 'POST',
            data: form.serialize(),
            success: function (res) {
                if (res.status) {
                    showToast('success', res.message || 'Courier updated successfully.');

                    setTimeout(function () {
                        window.location.reload();
                    }, 900);
                } else {
                    showToast('error', res.message || 'Courier update failed.');
                    button.prop('disabled', false).html(oldButtonHtml);
                }
            },
            error: function (xhr) {
                let message = 'Courier update failed.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors)[0][0];
                }

                showToast('error', message);
                button.prop('disabled', false).html(oldButtonHtml);
            }
        });
    });

    $(document).on('submit', '#orderStatusForm', function (e) {
        e.preventDefault();

        let form = $(this);
        let button = form.find('button[type="submit"]');
        let oldButtonHtml = button.html();

        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Updating...');

        $.ajax({
            url: form.data('url'),
            type: 'POST',
            data: form.serialize(),
            success: function (res) {
                if (res.status) {
                    showToast('success', res.message || 'Order status updated successfully.');

                    setTimeout(function () {
                        window.location.reload();
                    }, 900);
                } else {
                    showToast('error', res.message || 'Status update failed.');
                    button.prop('disabled', false).html(oldButtonHtml);
                }
            },
            error: function (xhr) {
                let message = 'Status update failed.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors)[0][0];
                }

                showToast('error', message);
                button.prop('disabled', false).html(oldButtonHtml);
            }
        });
    });

    $(document).on('click', '.btnSendSteadfast', function () {
        let button = $(this);
        let url = button.data('url');
        let oldButtonHtml = button.html();

        Swal.fire({
            title: 'Send this order to SteadFast?',
            text: 'A new SteadFast consignment will be created.',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send',
            confirmButtonColor: '#2563eb'
        }).then(function (result) {
            if (swalConfirmed(result)) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Sending...');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (res) {
                        if (res.status) {
                            showToast('success', res.message || 'Order sent to SteadFast successfully.');

                            setTimeout(function () {
                                window.location.reload();
                            }, 900);
                        } else {
                            showToast('error', res.message || 'SteadFast send failed.');
                            button.prop('disabled', false).html(oldButtonHtml);
                        }
                    },
                    error: function (xhr) {
                        let message = 'SteadFast send failed.';

                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }

                        showToast('error', message);
                        button.prop('disabled', false).html(oldButtonHtml);
                    }
                });
            }
        });
    });

    $(document).on('click', '.btnSendPathao', function () {
        let button = $(this);
        let url = button.data('url');
        let oldButtonHtml = button.html();

        Swal.fire({
            title: 'Send this order to Pathao?',
            text: 'A new Pathao order will be created.',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send',
            confirmButtonColor: '#16a34a'
        }).then(function (result) {
            if (swalConfirmed(result)) {
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Sending...');

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (res) {
                        if (res.status) {
                            showToast('success', res.message || 'Order sent to Pathao successfully.');

                            setTimeout(function () {
                                window.location.reload();
                            }, 900);
                        } else {
                            showToast('error', res.message || 'Pathao send failed.');
                            button.prop('disabled', false).html(oldButtonHtml);
                        }
                    },
                    error: function (xhr) {
                        let message = 'Pathao send failed.';

                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }

                        showToast('error', message);
                        button.prop('disabled', false).html(oldButtonHtml);
                    }
                });
            }
        });
    });

    $(document).on('click', '.btnSyncSteadfast', function () {
        let button = $(this);
        let url = button.data('url');
        let oldButtonHtml = button.html();

        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Syncing...');

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function (res) {
                if (res.status) {
                    showToast('success', res.message || 'SteadFast status synced successfully.');

                    setTimeout(function () {
                        window.location.reload();
                    }, 900);
                } else {
                    showToast('error', res.message || 'SteadFast sync failed.');
                    button.prop('disabled', false).html(oldButtonHtml);
                }
            },
            error: function (xhr) {
                let message = 'SteadFast sync failed.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                showToast('error', message);
                button.prop('disabled', false).html(oldButtonHtml);
            }
        });
    });
});
</script>
@endsection