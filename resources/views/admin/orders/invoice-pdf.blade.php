<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $order->invoice_id }}</title>

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 12px;
        color: #000000;
        background: #ffffff;
    }

    table {
        width: 100%;
        color: #000000 !important;
        border-collapse: collapse;
        margin-bottom: 10px;
    }

    table,
    th,
    td {
        border: 1px solid #6c757d !important;
    }

    th,
    td {
        padding: 5px !important;
        text-align: left;
        vertical-align: top;
    }

    thead tr th {
        background-color: #6c757d !important;
        color: #ffffff !important;
        font-weight: bold;
    }

    .invoice-title {
        font-size: 17px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .customer-phone {
        font-size: 21px;
        font-weight: bold;
        margin: 4px 0;
    }

    .product-name {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 2px;
    }

    .quantity-text {
        font-size: 20px;
        font-weight: bold;
    }

    .order-note {
        font-size: 14px;
        font-weight: bold;
        margin-top: 6px;
    }

    .logo {
        max-height: 45px;
        display: block;
        margin-bottom: 6px;
    }

    .text-muted {
        color: #6c757d;
    }

    .merchant-box {
        margin-top: 5px;
        font-size: 13px;
        font-weight: bold;
        line-height: 1.4;
    }

    .footer-note {
        margin-top: 10px;
        font-size: 11px;
        text-align: center;
        color: #6c757d;
    }

    hr {
        border: none;
        border-top: 1px dashed red;
        margin: 12px 0;
    }
    </style>
</head>

<body>

    @php
    $deliveryArea = $order->delivery_area
    ? ucwords(str_replace('_', ' ', $order->delivery_area))
    : '-';

    $paymentMethod = $order->payment_method
    ? ucwords(str_replace('_', ' ', $order->payment_method))
    : 'Cash On Delivery';

    $paymentStatus = $order->payment_status
    ? ucwords(str_replace('_', ' ', $order->payment_status))
    : '-';

    $orderStatus = $order->order_status
    ? ucwords(str_replace('_', ' ', $order->order_status))
    : '-';

    $courierName = $order->courier?->name
    ?? (($courierServices ?? config('couriers.list'))[$order->courier_service] ?? 'Not Selected');

    $courierMerchantId = $order->courier?->merchant_id;
    $courierPhoneNumber = $order->courier?->phone_number;

    $logoPath = null;

    if ($siteSetting && $siteSetting->getFirstMedia('site_logo')) {
    $logoPath = $siteSetting->getFirstMedia('site_logo')->getPath();
    }
    @endphp

    <table>
        <tr>
            <td style="width: 35%;">
                @if ($logoPath && file_exists($logoPath))
                <img src="{{ $logoPath }}" class="logo" alt="{{ $siteSetting->website_name }}">
                @else
                <h3>{{ $siteSetting->website_name ?? config('app.name') }}</h3>
                @endif

                <strong>{{ $siteSetting->website_name ?? config('app.name') }}</strong>

                @if ($siteSetting?->address)
                <br>
                <small>{{ $siteSetting->address }}</small>
                @endif

                @if ($siteSetting?->phone)
                <br>
                <small>Phone: {{ $siteSetting->phone }}</small>
                @endif

                @if ($siteSetting?->email)
                <br>
                <small>Email: {{ $siteSetting->email }}</small>
                @endif

                @if($courierName || $courierMerchantId || $courierPhoneNumber)
                <div class="merchant-box">
                    @if($courierName && $courierName !== 'Not Selected')
                    Courier Name: {{ $courierName }}<br>
                    @endif

                    @if($courierMerchantId)
                    Merchant ID: {{ $courierMerchantId }}<br>
                    @endif

                    @if($courierPhoneNumber)
                    Courier Phone: {{ $courierPhoneNumber }}
                    @endif
                </div>
                @endif
            </td>

            <td style="width: 35%;">
                <div class="invoice-title">CUSTOMER INFO</div>

                <strong>{{ $order->customer_name }}</strong><br>

                <div class="customer-phone">
                    {{ $order->phone }}
                </div>

                {{ $order->address }} <br>

                <strong>Area:</strong> {{ $deliveryArea }}
            </td>

            <td style="width: 30%;">
                <div class="invoice-title">
                    Invoice #{{ $order->invoice_id }}
                </div>

                <strong>Order Date:</strong>
                {{ $order->created_at ? $order->created_at->format('d M, Y h:i A') : '-' }}
                <br>

                <strong>Payment Method:</strong>
                {{ $paymentMethod }}
                <br>

                <strong>Payment Status:</strong>
                {{ $paymentStatus }}
                <br>

                <strong>Order Status:</strong>
                {{ $orderStatus }}
                <br>

                <strong>Courier:</strong>
                {{ $courierName }}
                <br>

                <strong>Employee:</strong>
                {{ $order->assignedEmployee->name ?? 'Unassigned' }}

                <div class="order-note">
                    Order Note:
                    {{ $order->customer_note ?? '-' }}
                </div>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 60%;">Product</th>
                <th style="width: 15%;">Quantity</th>
                <th style="width: 25%;">Price</th>
            </tr>
        </thead>

        <tbody>
            @forelse($order->items as $item)
            <tr>
                <td>
                    <div class="product-name">
                        {{ $item->product_name }}
                    </div>

                    @if (!empty($item->product_code))
                    <small class="text-muted">
                        Code: {{ $item->product_code }}
                    </small>
                    @endif
                </td>

                <td>
                    <div class="quantity-text">
                        {{ $item->quantity }}
                    </div>
                </td>

                <td>
                    {{ number_format($item->total_price ?? 0) }} Tk

                    @if (!empty($item->unit_price))
                    <br>
                    <small>
                        Unit: {{ number_format($item->unit_price) }} Tk
                    </small>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="3" style="text-align: center;">
                    No order items found.
                </td>
            </tr>
            @endforelse
        </tbody>

        <tfoot>
            <tr>
                <td style="border: none !important;"></td>
                <th>Sub Total:</th>
                <td>{{ number_format($order->sub_total ?? 0) }} Tk</td>
            </tr>

            <tr>
                <td style="border: none !important;"></td>
                <th>Delivery:</th>
                <td>
                    @if($order->is_free_delivery)
                    Free Delivery
                    @else
                    {{ number_format($order->shipping_charge ?? 0) }} Tk
                    @endif
                </td>
            </tr>

            @if (($order->cod_charge ?? 0) > 0)
            <tr>
                <td style="border: none !important;"></td>
                <th>COD Charge:</th>
                <td>{{ number_format($order->cod_charge ?? 0) }} Tk</td>
            </tr>
            @endif

            <tr>
                <td style="border: none !important;"></td>
                <th>Total:</th>
                <td>
                    <strong>{{ number_format($order->total_amount ?? 0) }} Tk</strong>
                </td>
            </tr>
        </tfoot>
    </table>

    <table>
        <tr>
            <td style="width: 50%;">
                <strong>Customer Note:</strong><br>
                {{ $order->customer_note ?? '-' }}
            </td>

            <td style="width: 50%;">
                <strong>Admin Note:</strong><br>
                {{ $order->admin_note ?? '-' }}
            </td>
        </tr>
    </table>

    <hr>

    <div class="footer-note">
        Generated by {{ config('app.name') }} |
        Invoice #{{ $order->invoice_id }}
    </div>

</body>

</html>