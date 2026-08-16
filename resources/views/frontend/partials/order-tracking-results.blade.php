@php
    $orderTrackingStatusLabels = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'complete_invoice' => 'Invoice Complete',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'courier_pending' => 'Courier Pending',
        'courier_cancelled' => 'Courier Cancel',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'canceled' => 'Cancelled',
        'fake' => 'Fake',
        'stock_out' => 'Stock Out',
    ];
@endphp

@if($trackingOrders !== null)
    @if($trackingOrders->isEmpty())
        <div class="alert alert-warning mt-4 mb-0">
            এই ফোন নম্বরে কোনো অর্ডার পাওয়া যায়নি।
        </div>
    @else
        <div class="mt-4">
            <div class="font-weight-bold text-muted">
                সর্বশেষ {{ $trackingOrders->count() }}টি অর্ডার
            </div>

            @foreach($trackingOrders as $order)
                @php
                    $isPathaoOrder = strtolower((string) $order->courier_service) === 'pathao'
                        || filled($order->pathao_consignment_id);

                    $rawCourierStatus = $isPathaoOrder
                        ? $order->pathao_status
                        : $order->steadfast_status;

                    $courierStatus = $rawCourierStatus
                        ? ucwords(str_replace('_', ' ', $rawCourierStatus))
                        : 'Not available yet';

                    $courierStatusLabel = $isPathaoOrder
                        ? 'Pathao Status'
                        : 'SteadFast Status';

                    $trackingCode = $isPathaoOrder
                        ? $order->pathao_consignment_id
                        : $order->steadfast_tracking_code;

                    $localStatus = $orderTrackingStatusLabels[$order->order_status]
                        ?? ucwords(str_replace('_', ' ', (string) $order->order_status));
                @endphp

                <div class="order-tracking-card">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div>
                            <div class="font-weight-bold text-dark">
                                Invoice: {{ $order->invoice_id }}
                            </div>
                            <div class="order-tracking-meta">
                                Order Date: {{ optional($order->local_created_at)->format('d M Y, h:i A') ?: '-' }}
                            </div>
                        </div>

                        <span class="order-tracking-status mt-2 mt-sm-0">{{ $localStatus }}</span>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6 order-tracking-meta">
                            <strong>Courier:</strong> {{ $order->courier_name }}<br>
                            <strong>{{ $courierStatusLabel }}:</strong> {{ $courierStatus }}<br>
                            <strong>Tracking Code:</strong> {{ $trackingCode ?: '-' }}
                        </div>
                        <div class="col-md-6 order-tracking-meta mt-2 mt-md-0">
                            <strong>Amount:</strong> ৳{{ number_format((float) $order->total_amount, 2) }}<br>
                            <strong>Payment:</strong> {{ ucwords(str_replace('_', ' ', (string) $order->payment_status)) }}<br>
                            <strong>Items:</strong>
                            {{ $order->items->map(fn ($item) => $item->quantity . '× ' . $item->product_name)->implode(', ') ?: '-' }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endif
