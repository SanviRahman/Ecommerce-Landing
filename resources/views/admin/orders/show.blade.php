@extends('adminlte::page')

@section('title', $title ?? 'Order Details')

@section('plugins.Sweetalert2', true)

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

        <div class="mt-2 mt-md-0">
            <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>

            <a href="{{ route('admin.orders.invoice', $order->id) }}" class="btn btn-primary btn-sm">
                <i class="fas fa-file-invoice mr-1"></i> Invoice
            </a>

            <a href="{{ route('admin.orders.invoice.download', $order->id) }}" class="btn btn-success btn-sm">
                <i class="fas fa-file-download mr-1"></i> Download PDF
            </a>

            @if(auth()->user()->isAdmin() && $order->courier_service === 'steadfast')
                @if(empty($order->steadfast_consignment_id))
                    <button type="button"
                            class="btn btn-info btn-sm btnSendSteadfast"
                            data-url="{{ route('admin.orders.send_steadfast', $order->id) }}">
                        <i class="fas fa-paper-plane mr-1"></i> Send SteadFast
                    </button>
                @else
                    <button type="button"
                            class="btn btn-warning btn-sm btnSyncSteadfast"
                            data-url="{{ route('admin.orders.sync_steadfast_status', $order->id) }}">
                        <i class="fas fa-sync-alt mr-1"></i> Sync SteadFast
                    </button>
                @endif
            @endif
        </div>
    </div>
@endsection

@section('content')

@php
    $courierList = $courierServices ?? config('couriers.list', []);

    $courierName = $order->courier_service
        ? ($courierList[$order->courier_service] ?? $order->courier_service)
        : 'Not Selected';

    $deliveryArea = $order->delivery_area
        ? ucwords(str_replace('_', ' ', $order->delivery_area))
        : '-';

    $orderStatusText = $order->order_status
        ? ucfirst(str_replace('_', ' ', $order->order_status))
        : '-';

    $paymentStatusText = $order->payment_status
        ? ucfirst(str_replace('_', ' ', $order->payment_status))
        : '-';

    $orderStatusClass = match ($order->order_status) {
        'pending' => 'badge-warning',
        'confirmed' => 'badge-primary',
        'processing' => 'badge-secondary',
        'shipped' => 'badge-info',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger',
        'fake' => 'badge-danger',
        default => 'badge-light border',
    };

    $paymentStatusClass = match ($order->payment_status) {
        'collected', 'paid' => 'badge-success',
        'cod_pending', 'unpaid' => 'badge-warning',
        'failed' => 'badge-danger',
        default => 'badge-info',
    };

    $steadfastStatusText = $order->steadfast_status
        ? ucwords(str_replace('_', ' ', $order->steadfast_status))
        : 'Not Synced';

    $steadfastStatusClass = match ($order->steadfast_status) {
        'delivered' => 'badge-success',
        'cancelled', 'partial_delivered_cancelled' => 'badge-danger',
        'in_review', 'hold', 'pending' => 'badge-warning',
        'in_transit', 'picked_up', 'assigned' => 'badge-info',
        default => 'badge-light border',
    };

    $steadfastSentAt = $order->steadfast_sent_at
        ? \Illuminate\Support\Carbon::parse($order->steadfast_sent_at)->format('d M, Y h:i A')
        : '-';

    $steadfastSyncedAt = $order->steadfast_synced_at
        ? \Illuminate\Support\Carbon::parse($order->steadfast_synced_at)->format('d M, Y h:i A')
        : '-';
@endphp

<div class="row">
    <div class="col-lg-8">

        {{-- Order Information --}}
        <div class="card shadow-sm border-0 mb-4 order-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-shopping-cart text-primary mr-2"></i>
                    Order Information
                </h5>
            </div>

            <div class="card-body">
                <table class="table table-bordered table-striped mb-0">
                    <tbody>
                    <tr>
                        <th width="30%">Invoice ID</th>
                        <td>
                            <span class="font-weight-bold text-dark">#{{ $order->invoice_id }}</span>
                        </td>
                    </tr>

                    <tr>
                        <th>Campaign</th>
                        <td>{{ $order->campaign->title ?? '-' }}</td>
                    </tr>

                    <tr>
                        <th>Assigned Employee</th>
                        <td>
                            @if($order->assignedEmployee)
                                <strong>{{ $order->assignedEmployee->name }}</strong>
                                <small class="text-muted">({{ $order->assignedEmployee->email }})</small>
                            @else
                                <span class="badge badge-light border">Unassigned</span>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <th>Order Status</th>
                        <td>
                            <span class="badge {{ $orderStatusClass }}">
                                {{ $orderStatusText }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <th>Payment Method</th>
                        <td>
                            {{ $order->payment_method ? ucfirst(str_replace('_', ' ', $order->payment_method)) : 'Cash On Delivery' }}
                        </td>
                    </tr>

                    <tr>
                        <th>Payment Status</th>
                        <td>
                            <span class="badge {{ $paymentStatusClass }}">
                                {{ $paymentStatusText }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <th>Courier Service</th>
                        <td>
                            @if($order->courier_service)
                                <span class="badge badge-info">
                                    {{ $courierName }}
                                </span>
                            @else
                                <span class="badge badge-light border">Not Selected</span>
                            @endif
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

                    @if($order->confirmed_at)
                        <tr>
                            <th>Confirmed At</th>
                            <td>{{ $order->confirmed_at->format('d M, Y h:i A') }}</td>
                        </tr>
                    @endif

                    @if($order->delivered_at)
                        <tr>
                            <th>Delivered At</th>
                            <td>{{ $order->delivered_at->format('d M, Y h:i A') }}</td>
                        </tr>
                    @endif

                    @if($order->cancelled_at)
                        <tr>
                            <th>Cancelled At</th>
                            <td>{{ $order->cancelled_at->format('d M, Y h:i A') }}</td>
                        </tr>
                    @endif

                    @if($order->marked_fake_at)
                        <tr>
                            <th>Marked Fake At</th>
                            <td>{{ $order->marked_fake_at->format('d M, Y h:i A') }}</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SteadFast Courier Information --}}
        @if($order->courier_service === 'steadfast')
            <div class="card shadow-sm border-0 mb-4 order-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-truck text-info mr-2"></i>
                        SteadFast Courier Information
                    </h5>

                    @if(auth()->user()->isAdmin())
                        <div>
                            @if(empty($order->steadfast_consignment_id))
                                <button type="button"
                                        class="btn btn-info btn-sm btnSendSteadfast"
                                        data-url="{{ route('admin.orders.send_steadfast', $order->id) }}">
                                    <i class="fas fa-paper-plane mr-1"></i> Send Now
                                </button>
                            @else
                                <button type="button"
                                        class="btn btn-warning btn-sm btnSyncSteadfast"
                                        data-url="{{ route('admin.orders.sync_steadfast_status', $order->id) }}">
                                    <i class="fas fa-sync-alt mr-1"></i> Sync Status
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="card-body">
                    <table class="table table-bordered table-striped mb-0">
                        <tbody>
                        <tr>
                            <th width="30%">Consignment ID</th>
                            <td>{{ $order->steadfast_consignment_id ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Tracking Code</th>
                            <td>
                                @if($order->steadfast_tracking_code)
                                    <span class="font-weight-bold text-success">
                                        {{ $order->steadfast_tracking_code }}
                                    </span>
                                @else
                                    <span class="badge badge-warning">Not Sent Yet</span>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>SteadFast Status</th>
                            <td>
                                <span class="badge {{ $steadfastStatusClass }}">
                                    {{ $steadfastStatusText }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <th>SteadFast Note</th>
                            <td>{{ $order->steadfast_note ?? '-' }}</td>
                        </tr>

                        <tr>
                            <th>Sent At</th>
                            <td>{{ $steadfastSentAt }}</td>
                        </tr>

                        <tr>
                            <th>Last Synced At</th>
                            <td>{{ $steadfastSyncedAt }}</td>
                        </tr>

                        @if(!empty($order->steadfast_response))
                            <tr>
                                <th>API Response</th>
                                <td>
                                    <details>
                                        <summary class="cursor-pointer text-primary font-weight-bold">
                                            View Raw Response
                                        </summary>

                                        <pre class="bg-light border rounded p-2 mt-2 mb-0 small response-box">{{ json_encode($order->steadfast_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Customer Information --}}
        <div class="card shadow-sm border-0 mb-4 order-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-user text-success mr-2"></i>
                    Customer Information
                </h5>
            </div>

            <div class="card-body">
                <table class="table table-bordered table-striped mb-0">
                    <tbody>
                    <tr>
                        <th width="30%">Customer Name</th>
                        <td>{{ $order->customer_name }}</td>
                    </tr>

                    <tr>
                        <th>Phone</th>
                        <td>
                            <strong>{{ $order->phone }}</strong>
                        </td>
                    </tr>

                    <tr>
                        <th>Address</th>
                        <td>{{ $order->address }}</td>
                    </tr>

                    <tr>
                        <th>Delivery Area</th>
                        <td>{{ $deliveryArea }}</td>
                    </tr>

                    <tr>
                        <th>Courier Service</th>
                        <td>{{ $courierName }}</td>
                    </tr>

                    @if($order->courier_service === 'steadfast')
                        <tr>
                            <th>SteadFast Tracking</th>
                            <td>{{ $order->steadfast_tracking_code ?? 'Not Sent Yet' }}</td>
                        </tr>
                    @endif

                    <tr>
                        <th>Customer Note</th>
                        <td>{{ $order->customer_note ?? '-' }}</td>
                    </tr>

                    <tr>
                        <th>Admin Note</th>
                        <td>
                            @if(auth()->user()->isAdmin())
                                <form id="adminNoteForm" action="{{ route('admin.orders.update_admin_note', $order->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')

                                    <textarea name="admin_note"
                                              class="form-control"
                                              rows="3"
                                              placeholder="Write admin note...">{{ $order->admin_note }}</textarea>

                                    <button type="submit" class="btn btn-success btn-sm mt-2">
                                        <i class="fas fa-save mr-1"></i> Save Note
                                    </button>
                                </form>
                            @else
                                {{ $order->admin_note ?? '-' }}
                            @endif
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Order Items --}}
        <div class="card shadow-sm border-0 mb-4 order-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-box text-warning mr-2"></i>
                    Order Items
                </h5>
            </div>

            <div class="card-body table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="thead-light">
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
                            <td>
                                <strong>{{ $item->product_name }}</strong>

                                @if($item->product)
                                    <br>
                                    <small class="text-muted">
                                        Product ID: {{ $item->product->id }}
                                    </small>
                                @endif
                            </td>

                            <td>{{ $item->quantity }}</td>
                            <td>৳{{ number_format($item->unit_price ?? 0, 2) }}</td>
                            <td>৳{{ number_format($item->total_price ?? 0, 2) }}</td>
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
                        <th>৳{{ number_format($order->sub_total ?? 0, 2) }}</th>
                    </tr>

                    <tr>
                        <th colspan="3" class="text-right">Shipping Charge</th>
                        <th>৳{{ number_format($order->shipping_charge ?? 0, 2) }}</th>
                    </tr>

                    <tr>
                        <th colspan="3" class="text-right">COD Charge</th>
                        <th>৳{{ number_format($order->cod_charge ?? 0, 2) }}</th>
                    </tr>

                    <tr class="bg-light">
                        <th colspan="3" class="text-right">Total Amount</th>
                        <th class="text-success">
                            ৳{{ number_format($order->total_amount ?? 0, 2) }}
                        </th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Logs --}}
        @if(isset($order->statusLogs) && $order->statusLogs->count())
            <div class="card shadow-sm border-0 mb-4 order-card">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-history text-info mr-2"></i>
                        Status Logs
                    </h5>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="thead-light">
                        <tr>
                            <th>Status</th>
                            <th>Note</th>
                            <th>Created By</th>
                            <th>Date</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($order->statusLogs as $log)
                            <tr>
                                <td>
                                    <span class="badge badge-info">
                                        {{ ucfirst(str_replace('_', ' ', $log->status)) }}
                                    </span>
                                </td>
                                <td>{{ $log->note ?? '-' }}</td>
                                <td>{{ $log->createdBy->name ?? '-' }}</td>
                                <td>{{ $log->created_at ? $log->created_at->format('d M, Y h:i A') : '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(isset($order->fakeLogs) && $order->fakeLogs->count())
            <div class="card shadow-sm border-0 mb-4 order-card">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold text-danger">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Fake Order Logs
                    </h5>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="thead-light">
                        <tr>
                            <th>Reason</th>
                            <th>Detected By</th>
                            <th>Date</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach($order->fakeLogs as $log)
                            <tr>
                                <td>{{ $log->fake_reason ?? '-' }}</td>
                                <td>{{ $log->detected_by ?? '-' }}</td>
                                <td>{{ $log->created_at ? $log->created_at->format('d M, Y h:i A') : '-' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Right Actions --}}
    <div class="col-lg-4">

        {{-- Quick Summary --}}
        <div class="card shadow-sm border-0 mb-4 order-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-info-circle text-primary mr-2"></i>
                    Quick Summary
                </h5>
            </div>

            <div class="card-body">
                <div class="summary-item">
                    <span>Invoice</span>
                    <strong>#{{ $order->invoice_id }}</strong>
                </div>

                <div class="summary-item">
                    <span>Total</span>
                    <strong class="text-success">৳{{ number_format($order->total_amount ?? 0, 2) }}</strong>
                </div>

                <div class="summary-item">
                    <span>Courier</span>
                    <strong>{{ $courierName }}</strong>
                </div>

                @if($order->courier_service === 'steadfast')
                    <div class="summary-item">
                        <span>Tracking</span>
                        <strong>{{ $order->steadfast_tracking_code ?? 'Not Sent' }}</strong>
                    </div>

                    <div class="summary-item">
                        <span>SF Status</span>
                        <span class="badge {{ $steadfastStatusClass }}">{{ $steadfastStatusText }}</span>
                    </div>
                @endif

                <div class="summary-item">
                    <span>Status</span>
                    <span class="badge {{ $orderStatusClass }}">{{ $orderStatusText }}</span>
                </div>
            </div>
        </div>

        {{-- SteadFast Action --}}
        @if(auth()->user()->isAdmin() && $order->courier_service === 'steadfast')
            <div class="card shadow-sm border-0 mb-4 order-card">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold">
                        <i class="fas fa-truck text-info mr-2"></i>
                        SteadFast Action
                    </h5>
                </div>

                <div class="card-body">
                    @if(empty($order->steadfast_consignment_id))
                        <p class="text-muted">
                            This order is selected for SteadFast courier but not sent yet.
                        </p>

                        <button type="button"
                                class="btn btn-info btn-block btnSendSteadfast"
                                data-url="{{ route('admin.orders.send_steadfast', $order->id) }}">
                            <i class="fas fa-paper-plane mr-1"></i> Send To SteadFast
                        </button>
                    @else
                        <p class="text-muted">
                            This order already sent to SteadFast. You can sync latest delivery status.
                        </p>

                        <button type="button"
                                class="btn btn-warning btn-block btnSyncSteadfast"
                                data-url="{{ route('admin.orders.sync_steadfast_status', $order->id) }}">
                            <i class="fas fa-sync-alt mr-1"></i> Sync SteadFast Status
                        </button>
                    @endif
                </div>
            </div>
        @endif

        {{-- Status Update --}}
        <div class="card shadow-sm border-0 mb-4 order-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-sync-alt text-primary mr-2"></i>
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
        <div class="card shadow-sm border-0 mb-4 order-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-money-check-alt text-success mr-2"></i>
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
            <div class="card shadow-sm border-0 mb-4 order-card">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold text-danger">
                        <i class="fas fa-ban mr-2"></i>
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
            <div class="card shadow-sm border-0 mb-4 order-card">
                <div class="card-header bg-white">
                    <h5 class="mb-0 font-weight-bold text-warning">
                        <i class="fas fa-undo mr-2"></i>
                        Restore Fake Order
                    </h5>
                </div>

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
        <div class="card shadow-sm border-0 order-card">
            <div class="card-header bg-white">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-cogs text-secondary mr-2"></i>
                    Technical Info
                </h5>
            </div>

            <div class="card-body">
                <p class="mb-2">
                    <strong>IP:</strong><br>
                    <span class="text-muted">{{ $order->source_ip ?? '-' }}</span>
                </p>

                <p class="mb-2">
                    <strong>Source URL:</strong><br>
                    <span class="text-muted break-text">{{ $order->source_url ?? '-' }}</span>
                </p>

                <p class="mb-0">
                    <strong>User Agent:</strong><br>
                    <span class="text-muted break-text">
                        {{ \Illuminate\Support\Str::limit($order->user_agent ?? '-', 160) }}
                    </span>
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

@section('js')
<script>
$(document).ready(function () {
    function swalConfirmed(result) {
        return result.isConfirmed || result.value;
    }

    function showToast(type, message) {
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
                        showToast('success', res.message || 'Action completed successfully.');

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
    ajaxForm('#adminNoteForm', false);

    /*
    |--------------------------------------------------------------------------
    | Send Single Order To SteadFast
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btnSendSteadfast', function () {
        let button = $(this);
        let url = button.data('url');

        Swal.fire({
            title: 'Send this order to SteadFast?',
            text: 'A new SteadFast consignment will be created.',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send',
            confirmButtonColor: '#2563eb'
        }).then((result) => {
            if (swalConfirmed(result)) {
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    beforeSend: function () {
                        button.prop('disabled', true);
                    },
                    complete: function () {
                        button.prop('disabled', false);
                    },
                    success: function (res) {
                        if (res.status) {
                            showToast('success', res.message || 'Order sent to SteadFast.');

                            setTimeout(function () {
                                window.location.reload();
                            }, 900);
                        } else {
                            showToast('error', res.message || 'SteadFast send failed.');
                        }
                    },
                    error: function (xhr) {
                        let message = 'SteadFast send failed.';

                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            message = xhr.responseJSON.message;
                        }

                        showToast('error', message);
                    }
                });
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Sync Single SteadFast Status
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btnSyncSteadfast', function () {
        let button = $(this);
        let url = button.data('url');

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            beforeSend: function () {
                button.prop('disabled', true);
            },
            complete: function () {
                button.prop('disabled', false);
            },
            success: function (res) {
                if (res.status) {
                    showToast('success', res.message || 'SteadFast status synced.');

                    setTimeout(function () {
                        window.location.reload();
                    }, 900);
                } else {
                    showToast('error', res.message || 'SteadFast sync failed.');
                }
            },
            error: function (xhr) {
                let message = 'SteadFast sync failed.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }

                showToast('error', message);
            }
        });
    });
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

    .order-card {
        border-radius: 12px;
        overflow: hidden;
    }

    .table th {
        background: #f8fafc;
        color: #374151;
        font-weight: 700;
        vertical-align: middle;
    }

    .table td {
        vertical-align: middle;
    }

    .summary-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #e5e7eb;
        padding: 10px 0;
    }

    .summary-item:first-child {
        padding-top: 0;
    }

    .summary-item:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .summary-item span {
        color: #6b7280;
        font-size: 13px;
    }

    .summary-item strong {
        color: #111827;
        font-size: 14px;
    }

    .break-text {
        word-break: break-word;
    }

    .cursor-pointer {
        cursor: pointer;
    }

    .response-box {
        max-height: 260px;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>
@endsection