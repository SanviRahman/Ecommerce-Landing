@extends('adminlte::page')

@section('title', $title ?? 'Order Details')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h1 class="mb-0">Order Details</h1>
        <small class="text-muted">Invoice: #{{ $order->invoice_id }}</small>
    </div>

    <div class="mt-2 mt-md-0">
        <a href="{{ route('admin.orders.invoice', $order->id) }}" target="_blank" class="btn btn-info mr-1">
            <i class="fas fa-file-invoice mr-1"></i>
            View Invoice
        </a>

        <a href="{{ url()->previous() ?: route('admin.orders.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i>
            Back
        </a>
    </div>
</div>
@endsection

@section('content')
@php
    $statusBadgeClass = function (?string $status): string {
        return match ($status) {
            'pending' => 'badge-warning',
            'confirmed' => 'badge-primary',
            'processing' => 'badge-primary order-processing-badge',
            'shipped' => 'badge-info',
            'delivered' => 'badge-success',
            'cancelled' => 'badge-danger',
            'fake' => 'badge-dark',
            default => 'badge-light border',
        };
    };

    $paymentBadgeClass = function (?string $status): string {
        return match ($status) {
            'cod_pending' => 'badge-warning',
            'collected' => 'badge-success',
            'failed' => 'badge-danger',
            'unpaid' => 'badge-secondary',
            default => 'badge-light border',
        };
    };

    $formatText = function ($value, string $default = '-'): string {
        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    };

    $jsonPretty = function ($value): string {
        if (empty($value)) {
            return '-';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-';
        }

        return (string) $value;
    };

    $firstProductImage = $order->first_product_image_url ?? null;
@endphp

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        {{-- Order Summary --}}
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-receipt text-primary mr-1"></i>
                    Order Information
                </h3>

                <div class="mt-2 mt-md-0">
                    <span class="badge {{ $statusBadgeClass($order->order_status) }} px-3 py-2">
                        {{ ucwords(str_replace('_', ' ', $order->order_status ?? 'N/A')) }}
                    </span>

                    @if($order->is_fake)
                        <span class="badge badge-danger px-3 py-2 ml-1">Fake Order</span>
                    @endif
                </div>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-box-lite">
                            <div class="label">Invoice ID</div>
                            <div class="value">#{{ $order->invoice_id }}</div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="info-box-lite">
                            <div class="label">Success Token</div>
                            <div class="value text-break">{{ $formatText($order->success_token) }}</div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="info-box-lite">
                            <div class="label">Campaign</div>
                            <div class="value">{{ $order->campaign->title ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="info-box-lite">
                            <div class="label">Order Field</div>
                            <div class="value">
                                @if($order->orderField)
                                    <span class="badge text-white" style="background: {{ $order->orderField->color ?: '#2563eb' }};">
                                        {{ $order->orderField->name }}
                                    </span>
                                @else
                                    -
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="info-box-lite">
                            <div class="label">Payment Method</div>
                            <div class="value">{{ ucwords(str_replace('_', ' ', $order->payment_method ?? '-')) }}</div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="info-box-lite">
                            <div class="label">Payment Status</div>
                            <div class="value">
                                <span class="badge {{ $paymentBadgeClass($order->payment_status) }} px-2 py-1">
                                    {{ ucwords(str_replace('_', ' ', $order->payment_status ?? '-')) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="info-box-lite">
                            <div class="label">Created At</div>
                            <div class="value">{{ $order->created_at?->format('d M Y h:i A') ?: '-' }}</div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="info-box-lite">
                            <div class="label">Updated At</div>
                            <div class="value">{{ $order->updated_at?->format('d M Y h:i A') ?: '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Customer Information --}}
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-user text-success mr-1"></i>
                    Customer Information
                </h3>
            </div>

            <div class="card-body">
                <table class="table table-bordered mb-0 order-show-table">
                    <tr>
                        <th width="220">Customer Name</th>
                        <td>{{ $order->customer_name }}</td>
                    </tr>
                    <tr>
                        <th>Phone</th>
                        <td><a href="tel:{{ $order->phone }}">{{ $order->phone }}</a></td>
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
                        <th>Customer Note</th>
                        <td>{{ $order->customer_note ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Admin Note</th>
                        <td>{{ $order->admin_note ?: '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Ordered Products --}}
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-box text-warning mr-1"></i>
                    Ordered Products
                </h3>

                @if($firstProductImage)
                    <img src="{{ $firstProductImage }}" alt="First Product" class="order-first-product-preview">
                @endif
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-bordered mb-0 order-show-table">
                    <thead class="bg-light">
                        <tr>
                            <th width="80">Image</th>
                            <th>Product</th>
                            <th width="120">Product ID</th>
                            <th width="130">Unit Price</th>
                            <th width="90">Qty</th>
                            <th width="140">Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($order->items as $item)
                            @php
                                $itemImage = $item->product_image_url ?? null;
                            @endphp

                            <tr>
                                <td>
                                    @if($itemImage)
                                        <img src="{{ $itemImage }}" alt="{{ $item->product_name }}" class="order-product-img">
                                    @else
                                        <div class="order-product-img-placeholder"><i class="fas fa-image"></i></div>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-weight-bold">{{ $item->product_name }}</div>
                                    @if($item->product)
                                        <small class="text-muted">Current product: {{ $item->product->name ?? '-' }}</small>
                                    @endif
                                </td>
                                <td>{{ $item->product_id ?: '-' }}</td>
                                <td>৳{{ number_format($item->unit_price ?? 0) }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td class="font-weight-bold">৳{{ number_format($item->total_price ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No products found.</td>
                            </tr>
                        @endforelse
                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="5" class="text-right">Subtotal</th>
                            <th>৳{{ number_format($order->sub_total ?? 0) }}</th>
                        </tr>
                        <tr>
                            <th colspan="5" class="text-right">Delivery Charge</th>
                            <th>
                                @if($order->is_free_delivery)
                                    <span class="text-success">Free Delivery</span>
                                @else
                                    ৳{{ number_format($order->shipping_charge ?? 0) }}
                                @endif
                            </th>
                        </tr>
                        <tr>
                            <th colspan="5" class="text-right">COD Charge</th>
                            <th>৳{{ number_format($order->cod_charge ?? 0) }}</th>
                        </tr>
                        <tr>
                            <th colspan="5" class="text-right">Grand Total</th>
                            <th class="text-success h5 mb-0">৳{{ number_format($order->total_amount ?? 0) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Source / Tracking Information --}}
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-link text-info mr-1"></i>
                    Source & Tracking Information
                </h3>
            </div>

            <div class="card-body">
                <table class="table table-bordered mb-0 order-show-table">
                    <tr><th width="220">Source IP</th><td>{{ $formatText($order->source_ip) }}</td></tr>
                    <tr><th>Source URL</th><td class="text-break">{{ $formatText($order->source_url) }}</td></tr>
                    <tr><th>User Agent</th><td class="text-break">{{ $formatText($order->user_agent) }}</td></tr>
                    <tr><th>Confirmed At</th><td>{{ $order->confirmed_at?->format('d M Y h:i A') ?: '-' }}</td></tr>
                    <tr><th>Delivered At</th><td>{{ $order->delivered_at?->format('d M Y h:i A') ?: '-' }}</td></tr>
                    <tr><th>Cancelled At</th><td>{{ $order->cancelled_at?->format('d M Y h:i A') ?: '-' }}</td></tr>
                    <tr><th>Marked Fake At</th><td>{{ $order->marked_fake_at?->format('d M Y h:i A') ?: '-' }}</td></tr>
                </table>
            </div>
        </div>

        {{-- Status Logs --}}
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-history text-primary mr-1"></i>
                    Status Logs
                </h3>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-bordered mb-0 order-show-table">
                    <thead class="bg-light">
                        <tr>
                            <th>Status</th>
                            <th>Note</th>
                            <th>Created By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->statusLogs as $log)
                            <tr>
                                <td><span class="badge {{ $statusBadgeClass($log->status) }}">{{ ucwords(str_replace('_', ' ', $log->status)) }}</span></td>
                                <td>{{ $log->note ?: '-' }}</td>
                                <td>{{ $log->created_by ?: '-' }}</td>
                                <td>{{ $log->created_at?->format('d M Y h:i A') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No status logs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Fake Logs --}}
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-ban text-danger mr-1"></i>
                    Fake Order Logs
                </h3>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-bordered mb-0 order-show-table">
                    <thead class="bg-light">
                        <tr>
                            <th>Reason</th>
                            <th>Detected By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->fakeLogs as $fakeLog)
                            <tr>
                                <td>{{ $fakeLog->fake_reason ?: '-' }}</td>
                                <td>{{ $fakeLog->detected_by ?: '-' }}</td>
                                <td>{{ $fakeLog->created_at?->format('d M Y h:i A') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No fake logs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Amount Summary --}}
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-money-bill text-success mr-1"></i>
                    Amount Summary
                </h3>
            </div>
            <div class="card-body">
                <div class="amount-row"><span>Subtotal</span><strong>৳{{ number_format($order->sub_total ?? 0) }}</strong></div>
                <div class="amount-row"><span>Shipping</span><strong>{{ $order->is_free_delivery ? 'Free' : '৳' . number_format($order->shipping_charge ?? 0) }}</strong></div>
                <div class="amount-row"><span>COD Charge</span><strong>৳{{ number_format($order->cod_charge ?? 0) }}</strong></div>
                <hr>
                <div class="amount-row grand"><span>Grand Total</span><strong>৳{{ number_format($order->total_amount ?? 0) }}</strong></div>
            </div>
        </div>

        {{-- Assignment / Courier --}}
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-truck text-info mr-1"></i>
                    Assignment & Courier
                </h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0 order-show-table">
                    <tr><th>Employee</th><td>{{ $order->assignedEmployee->name ?? 'Unassigned' }}</td></tr>
                    <tr><th>Employee Email</th><td>{{ $order->assignedEmployee->email ?? '-' }}</td></tr>
                    <tr><th>Courier</th><td>{{ $order->courier->name ?? $order->courier_name }}</td></tr>
                    <tr><th>Courier Service</th><td>{{ $formatText($order->courier_service) }}</td></tr>
                    <tr><th>Courier Account</th><td>{{ $order->courierAccount->name ?? '-' }}</td></tr>
                </table>
            </div>
        </div>

        {{-- Current Status --}}
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-info-circle text-primary mr-1"></i>
                    Current Status
                </h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0 order-show-table">
                    <tr>
                        <th>Order Status</th>
                        <td><span class="badge {{ $statusBadgeClass($order->order_status) }}">{{ ucwords(str_replace('_', ' ', $order->order_status ?? '-')) }}</span></td>
                    </tr>
                    <tr>
                        <th>Payment Status</th>
                        <td><span class="badge {{ $paymentBadgeClass($order->payment_status) }}">{{ ucwords(str_replace('_', ' ', $order->payment_status ?? '-')) }}</span></td>
                    </tr>
                    <tr><th>Free Delivery</th><td>{{ $order->is_free_delivery ? 'Yes' : 'No' }}</td></tr>
                    <tr><th>Fake Status</th><td>{{ $order->is_fake ? 'Fake' : 'Real' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Courier API Information --}}
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-truck text-primary mr-1"></i>
                    SteadFast Information
                </h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0 order-show-table">
                    <tr><th width="200">Consignment ID</th><td>{{ $formatText($order->steadfast_consignment_id) }}</td></tr>
                    <tr><th>Tracking Code</th><td>{{ $formatText($order->steadfast_tracking_code) }}</td></tr>
                    <tr><th>Status</th><td>{{ $formatText($order->steadfast_status) }}</td></tr>
                    <tr><th>Note</th><td>{{ $formatText($order->steadfast_note) }}</td></tr>
                    <tr><th>Sent At</th><td>{{ $order->steadfast_sent_at?->format('d M Y h:i A') ?: '-' }}</td></tr>
                    <tr><th>Synced At</th><td>{{ $order->steadfast_synced_at?->format('d M Y h:i A') ?: '-' }}</td></tr>
                </table>

                <div class="mt-3">
                    <label class="font-weight-bold">SteadFast Response</label>
                    <pre class="json-box mb-0">{{ $jsonPretty($order->steadfast_response) }}</pre>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 mb-3 order-view-card">
            <div class="card-header bg-white">
                <h3 class="card-title mb-0 font-weight-bold">
                    <i class="fas fa-shipping-fast text-success mr-1"></i>
                    Pathao Information
                </h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0 order-show-table">
                    <tr><th width="200">Consignment ID</th><td>{{ $formatText($order->pathao_consignment_id) }}</td></tr>
                    <tr><th>Merchant Order ID</th><td>{{ $formatText($order->pathao_merchant_order_id) }}</td></tr>
                    <tr><th>Status</th><td>{{ $formatText($order->pathao_status) }}</td></tr>
                    <tr><th>Delivery Fee</th><td>৳{{ number_format($order->pathao_delivery_fee ?? 0) }}</td></tr>
                    <tr><th>Note</th><td>{{ $formatText($order->pathao_note) }}</td></tr>
                    <tr><th>Sent At</th><td>{{ $order->pathao_sent_at?->format('d M Y h:i A') ?: '-' }}</td></tr>
                    <tr><th>Synced At</th><td>{{ $order->pathao_synced_at?->format('d M Y h:i A') ?: '-' }}</td></tr>
                </table>

                <div class="mt-3">
                    <label class="font-weight-bold">Pathao Response</label>
                    <pre class="json-box mb-0">{{ $jsonPretty($order->pathao_response) }}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
.order-view-card { border-radius: 12px; overflow: hidden; }
.info-box-lite {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px;
    background: #f8fafc;
    min-height: 72px;
}
.info-box-lite .label {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 700;
    margin-bottom: 4px;
}
.info-box-lite .value {
    font-weight: 700;
    color: #0f172a;
}
.order-show-table th,
.order-show-table td { vertical-align: middle !important; }
.order-product-img,
.order-first-product-preview {
    width: 58px;
    height: 58px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
}
.order-first-product-preview { width: 46px; height: 46px; }
.order-product-img-placeholder {
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
.amount-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed #e5e7eb;
}
.amount-row:last-child { border-bottom: 0; }
.amount-row.grand {
    font-size: 18px;
    color: #16a34a;
}
.json-box {
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    font-size: 12px;
    max-height: 320px;
    overflow: auto;
    white-space: pre-wrap;
}
.order-processing-badge {
    background: #ec00ff !important;
    color: #ffffff;
}
</style>
@endsection
