@extends('adminlte::page')

@section('title', $title ?? 'Order Details')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-0">{{ $title ?? 'Order Details' }}</h1>

            @if(isset($breadcrumb))
                <ol class="breadcrumb mt-2 mb-0 bg-transparent p-0">
                    @foreach($breadcrumb as $item)
                        <li class="breadcrumb-item">
                            <a href="{{ $item['url'] }}">{{ $item['text'] }}</a>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        <div>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>

            <a href="{{ route('admin.orders.invoice', $order->id) }}" class="btn btn-primary btn-sm">
                <i class="fas fa-file-invoice mr-1"></i> Invoice
            </a>
        </div>
    </div>
@endsection

@section('content')

    <div class="row">
        <div class="col-lg-8">
            {{-- Order Information --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-shopping-cart text-primary mr-2"></i>
                        Order Information
                    </h5>
                </div>

                <div class="card-body">
                    <table class="table table-bordered">
                        <tbody>
                        <tr>
                            <th width="30%">Invoice ID</th>
                            <td>#{{ $order->invoice_id }}</td>
                        </tr>

                        <tr>
                            <th>Campaign</th>
                            <td>{{ $order->campaign->title ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Assigned Employee</th>
                            <td>
                                @if($order->assignedEmployee)
                                    {{ $order->assignedEmployee->name }}
                                    <small class="text-muted">({{ $order->assignedEmployee->email }})</small>
                                @else
                                    <span class="badge badge-light border">Unassigned</span>
                                @endif
                            </td>
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
                                <span class="badge badge-info">
                                    {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <th>Fake Status</th>
                            <td>
                                @if($order->is_fake || $order->order_status === 'fake')
                                    <span class="badge badge-danger">Fake Order</span>
                                @else
                                    <span class="badge badge-success">Real Order</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>Created At</th>
                            <td>{{ $order->created_at ? $order->created_at->format('d M, Y h:i A') : '-' }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Customer Information --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-user text-success mr-2"></i>
                        Customer Information
                    </h5>
                </div>

                <div class="card-body">
                    <table class="table table-bordered">
                        <tbody>
                        <tr>
                            <th width="30%">Customer Name</th>
                            <td>{{ $order->customer_name }}</td>
                        </tr>

                        <tr>
                            <th>Phone</th>
                            <td>{{ $order->phone }}</td>
                        </tr>

                        <tr>
                            <th>Address</th>
                            <td>{{ $order->address }}</td>
                        </tr>

                        <tr>
                            <th>Delivery Area</th>
                            <td>{{ $order->delivery_area ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Customer Note</th>
                            <td>{{ $order->customer_note ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Admin Note</th>
                            <td>{{ $order->admin_note ?? '-' }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Order Items --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-box text-warning mr-2"></i>
                        Order Items
                    </h5>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>Product</th>
                            <th width="15%">Qty</th>
                            <th width="20%">Unit Price</th>
                            <th width="20%">Total</th>
                        </tr>
                        </thead>

                        <tbody>
                        @forelse($order->items as $item)
                            <tr>
                                <td>{{ $item->product_name }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ number_format($item->unit_price ?? 0, 2) }}</td>
                                <td>{{ number_format($item->total_price ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    No order items found.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>

                        <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">Sub Total</th>
                            <th>{{ number_format($order->sub_total ?? 0, 2) }}</th>
                        </tr>

                        <tr>
                            <th colspan="3" class="text-right">Shipping Charge</th>
                            <th>{{ number_format($order->shipping_charge ?? 0, 2) }}</th>
                        </tr>

                        <tr>
                            <th colspan="3" class="text-right">COD Charge</th>
                            <th>{{ number_format($order->cod_charge ?? 0, 2) }}</th>
                        </tr>

                        <tr>
                            <th colspan="3" class="text-right">Total Amount</th>
                            <th>{{ number_format($order->total_amount ?? 0, 2) }}</th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right Actions --}}
        <div class="col-lg-4">
            {{-- Status Update --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        Update Order Status
                    </h5>
                </div>

                <div class="card-body">
                    <form id="orderStatusForm" action="{{ route('admin.orders.update_status', $order->id) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="form-group">
                            <label>Order Status</label>
                            <select name="order_status" class="form-control">
                                @foreach($orderStatuses ?? [] as $status)
                                    <option value="{{ $status }}" {{ $order->order_status === $status ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Note</label>
                            <textarea name="note" class="form-control" rows="3" placeholder="Optional note..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save mr-1"></i> Update Status
                        </button>
                    </form>
                </div>
            </div>

            {{-- Payment Update --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        Update Payment Status
                    </h5>
                </div>

                <div class="card-body">
                    <form id="paymentStatusForm" action="{{ route('admin.orders.update_payment_status', $order->id) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="form-group">
                            <label>Payment Status</label>
                            <select name="payment_status" class="form-control">
                                @foreach($paymentStatuses ?? [] as $status)
                                    <option value="{{ $status }}" {{ $order->payment_status === $status ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-save mr-1"></i> Update Payment
                        </button>
                    </form>
                </div>
            </div>

            {{-- Fake Order --}}
            @if(! $order->is_fake && $order->order_status !== 'fake')
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 font-weight-bold text-danger">
                            Mark As Fake
                        </h5>
                    </div>

                    <div class="card-body">
                        <form id="markFakeForm" action="{{ route('admin.orders.mark_as_fake', $order->id) }}" method="POST">
                            @csrf
                            @method('PATCH')

                            <div class="form-group">
                                <label>Fake Reason</label>
                                <textarea name="fake_reason" class="form-control" rows="3" required></textarea>
                            </div>

                            <button type="submit" class="btn btn-danger btn-block">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Mark As Fake
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <form id="restoreFakeForm" action="{{ route('admin.orders.restore_fake', $order->id) }}" method="POST">
                            @csrf
                            @method('PATCH')

                            <button type="submit" class="btn btn-warning btn-block">
                                <i class="fas fa-undo mr-1"></i> Restore Fake Order
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Technical Info --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        Technical Info
                    </h5>
                </div>

                <div class="card-body">
                    <p class="mb-2">
                        <strong>IP:</strong>
                        <span class="text-muted">{{ $order->source_ip ?? '-' }}</span>
                    </p>

                    <p class="mb-2">
                        <strong>Source URL:</strong>
                        <span class="text-muted">{{ $order->source_url ?? '-' }}</span>
                    </p>

                    <p class="mb-0">
                        <strong>User Agent:</strong>
                        <span class="text-muted">{{ Str::limit($order->user_agent ?? '-', 100) }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('footer')
    <strong>
        © Copyright 2026 All rights reserved |
        This website developed by
        <a href="https://sfashanto.netlify.app/" target="_blank">SFA Shanto</a>
    </strong>
@endsection

@section('plugins.Sweetalert2', true)

@section('js')
    <script>
        $(document).ready(function () {
            function showToast(type, message) {
                Swal.fire({
                    icon: type,
                    title: message,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true,
                    toast: true
                });
            }

            function ajaxForm(formSelector, successReload = true) {
                $(document).on('submit', formSelector, function (e) {
                    e.preventDefault();

                    let form = $(this);
                    let btn = form.find('button[type="submit"]');

                    $.ajax({
                        url: form.attr('action'),
                        type: 'POST',
                        data: form.serialize(),

                        beforeSend: function () {
                            btn.prop('disabled', true);
                        },

                        complete: function () {
                            btn.prop('disabled', false);
                        },

                        success: function (res) {
                            if (res.status) {
                                showToast('success', res.message);

                                if (successReload) {
                                    setTimeout(function () {
                                        window.location.reload();
                                    }, 900);
                                }
                            } else {
                                showToast('error', res.message || 'Action failed.');
                            }
                        },

                        error: function (xhr) {
                            let message = 'Action failed.';

                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                message = xhr.responseJSON.message;
                            }

                            if (xhr.status === 422 && xhr.responseJSON.errors) {
                                message = Object.values(xhr.responseJSON.errors)[0][0];
                            }

                            showToast('error', message);
                        }
                    });
                });
            }

            ajaxForm('#orderStatusForm');
            ajaxForm('#paymentStatusForm');
            ajaxForm('#markFakeForm');
            ajaxForm('#restoreFakeForm');
        });
    </script>
@endsection

@section('css')
    <style>
        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
        }

        .swal2-container {
            z-index: 999999 !important;
        }
    </style>
@endsection