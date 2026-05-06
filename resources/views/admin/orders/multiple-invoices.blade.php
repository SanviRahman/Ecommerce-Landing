<!doctype html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Selected Invoices' }}</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
        }

        .no-print {
            padding: 10px;
            text-align: right;
            border-bottom: 1px solid #ddd;
            margin-bottom: 8px;
        }

        .btn-print {
            background: #2563eb;
            color: #fff;
            border: 0;
            padding: 8px 18px;
            cursor: pointer;
            border-radius: 4px;
        }

        .invoice-card {
            height: 92mm;
            overflow: hidden;
            padding: 5mm;
            border-bottom: 1px dashed #dc2626;
            page-break-inside: avoid;
        }

        .invoice-card:nth-child(3n) {
            page-break-after: always;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        table,
        th,
        td {
            border: 1px solid #6c757d;
        }

        th,
        td {
            padding: 3px;
            vertical-align: top;
            text-align: left;
        }

        th {
            background: #6c757d;
            color: #fff;
            font-weight: bold;
        }

        .logo {
            max-height: 28px;
            display: block;
            margin-bottom: 3px;
        }

        .invoice-title {
            font-size: 13px;
            font-weight: bold;
        }

        .phone {
            font-size: 15px;
            font-weight: bold;
        }

        .product-name {
            font-size: 12px;
            font-weight: bold;
        }

        .footer-note {
            text-align: center;
            font-size: 9px;
            color: #555;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm;
            }

            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
            }

            .invoice-card {
                height: 92mm;
                padding: 3mm;
            }

            th {
                background: #6c757d !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

<div class="no-print">
    <button onclick="window.print()" class="btn-print">
        Print Selected Invoices
    </button>
</div>

@php
    $logoUrl = null;

    if ($siteSetting && $siteSetting->getFirstMedia('site_logo')) {
        $logoUrl = $siteSetting->logo;
    }
@endphp

@foreach($orders as $order)
    @php
        $deliveryArea = $order->delivery_area
            ? ucwords(str_replace('_', ' ', $order->delivery_area))
            : '-';

        $paymentStatus = $order->payment_status
            ? ucwords(str_replace('_', ' ', $order->payment_status))
            : '-';

        $orderStatus = $order->order_status
            ? ucwords(str_replace('_', ' ', $order->order_status))
            : '-';

        $courierName = $courierServices[$order->courier_service] ?? 'Not Selected';
    @endphp

    <div class="invoice-card">
        <table>
            <tr>
                <td style="width: 34%;">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" class="logo" alt="{{ $siteSetting->website_name }}">
                    @endif

                    <strong>{{ $siteSetting->website_name ?? config('app.name') }}</strong>

                    @if($siteSetting?->address)
                        <br>{{ $siteSetting->address }}
                    @endif

                    @if($siteSetting?->phone)
                        <br>Phone: {{ $siteSetting->phone }}
                    @endif
                </td>

                <td style="width: 33%;">
                    <div class="invoice-title">CUSTOMER INFO</div>
                    <strong>{{ $order->customer_name }}</strong><br>
                    <div class="phone">{{ $order->phone }}</div>
                    {{ $order->address }}<br>
                    <strong>Area:</strong> {{ $deliveryArea }}
                </td>

                <td style="width: 33%;">
                    <div class="invoice-title">Invoice #{{ $order->invoice_id }}</div>
                    <strong>Date:</strong> {{ $order->created_at ? $order->created_at->format('d M, Y h:i A') : '-' }}<br>
                    <strong>Status:</strong> {{ $orderStatus }}<br>
                    <strong>Payment:</strong> {{ $paymentStatus }}<br>
                    <strong>Courier:</strong> {{ $courierName }}<br>
                    <strong>Employee:</strong> {{ $order->assignedEmployee->name ?? 'Unassigned' }}
                </td>
            </tr>
        </table>

        <table>
            <thead>
            <tr>
                <th style="width: 60%;">Product</th>
                <th style="width: 15%;">Qty</th>
                <th style="width: 25%;">Price</th>
            </tr>
            </thead>

            <tbody>
            @forelse($order->items as $item)
                <tr>
                    <td>
                        <div class="product-name">{{ $item->product_name }}</div>
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td>
                        {{ number_format($item->total_price ?? 0) }} Tk
                        @if(!empty($item->unit_price))
                            <br><small>Unit: {{ number_format($item->unit_price) }} Tk</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align:center;">No items found.</td>
                </tr>
            @endforelse
            </tbody>

            <tfoot>
            <tr>
                <td style="border:none;"></td>
                <th>Sub Total:</th>
                <td>{{ number_format($order->sub_total ?? 0) }} Tk</td>
            </tr>
            <tr>
                <td style="border:none;"></td>
                <th>Delivery:</th>
                <td>{{ number_format($order->shipping_charge ?? 0) }} Tk</td>
            </tr>
            <tr>
                <td style="border:none;"></td>
                <th>Total:</th>
                <td><strong>{{ number_format($order->total_amount ?? 0) }} Tk</strong></td>
            </tr>
            </tfoot>
        </table>

        <table>
            <tr>
                <td style="width: 50%;">
                    <strong>Customer Note:</strong> {{ $order->customer_note ?? '-' }}
                </td>
                <td style="width: 50%;">
                    <strong>Admin Note:</strong> {{ $order->admin_note ?? '-' }}
                </td>
            </tr>
        </table>

        <div class="footer-note">
            Generated by {{ config('app.name') }} | Invoice #{{ $order->invoice_id }}
        </div>
    </div>
@endforeach

</body>
</html>