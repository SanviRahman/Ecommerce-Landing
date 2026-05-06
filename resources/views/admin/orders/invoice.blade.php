@extends('adminlte::page')

@section('title', $title ?? 'Order Invoice')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap no-print">
        <h1 class="mb-0">Invoice</h1>

        <div>
            <a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>

            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="fa fa-print mr-1"></i> Print
            </button>
        </div>
    </div>
@endsection

@section('content')

@php
    $siteSetting = $siteSetting ?? \App\Models\SiteSetting::query()
        ->where('status', true)
        ->latest()
        ->first();

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

    $courierName = ($courierServices ?? config('couriers.list'))[$order->courier_service] ?? 'Not Selected';
@endphp

<div id="invoiceArea">
    <div class="invoice-section">

        <table class="invoice-table table table-striped" cellspacing="0" cellpadding="0">
            <tr>
                <td style="width: 35%;">
                    @if ($siteSetting && $siteSetting->getFirstMedia('site_logo'))
                        <img src="{{ $siteSetting->logo }}"
                             style="max-height:45px;display:block;margin-bottom:6px;"
                             alt="{{ $siteSetting->website_name }}">
                    @else
                        <h3 class="mb-1">{{ config('app.name') }}</h3>
                    @endif

                    <strong>
                        {{ $siteSetting->website_name ?? config('app.name') }}
                    </strong>

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
                </td>

                <td style="width: 35%;">
                    <h4 class="invoice-title">CUSTOMER INFO</h4>

                    <strong>{{ $order->customer_name }}</strong><br>

                    <h3 class="customer-phone">
                        {{ $order->phone }}
                    </h3>

                    {{ $order->address }} <br>

                    <strong>Area:</strong> {{ $deliveryArea }}
                </td>

                <td style="width: 30%;">
                    <h4 class="invoice-title">
                        Invoice #{{ $order->invoice_id }}
                    </h4>

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
                    <br>

                    <h4 class="order-note">
                        Order Note:
                        {{ $order->customer_note ?? '-' }}
                    </h4>
                </td>
            </tr>
        </table>

        <table class="invoice-table table table-striped">
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
                            <span class="d-block">
                                <h3 class="product-name">
                                    {{ $item->product_name }}
                                </h3>
                            </span>

                            @if (!empty($item->product_code))
                                <small class="text-muted">
                                    Code: {{ $item->product_code }}
                                </small>
                            @endif
                        </td>

                        <td>
                            <h3 class="quantity-text">
                                {{ $item->quantity }}
                            </h3>
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
                        <td colspan="3" class="text-center text-muted">
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
                    <td>{{ number_format($order->shipping_charge ?? 0) }} Tk</td>
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

        <table class="invoice-table table table-striped">
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

    </div>
</div>

@endsection

@section('footer')
    <strong class="no-print">
        © Copyright 2026 All rights reserved |
        This website developed by
        <a href="https://sfashanto.netlify.app/" target="_blank">SFA Shanto</a>
    </strong>
@endsection

@section('css')
<style>
    * {
        margin: 0;
        padding: 0;
    }

    #invoiceArea {
        background: #ffffff;
        color: #000000;
        padding: 12px;
    }

    .invoice-section {
        width: 100%;
        background: #ffffff;
    }

    .invoice-table {
        width: 100%;
        color: #000000 !important;
        margin-bottom: 10px;
        background: #ffffff;
    }

    .invoice-table,
    .invoice-table th,
    .invoice-table td {
        border: 1px solid #6c757d !important;
    }

    .invoice-table th,
    .invoice-table td {
        padding: 4px !important;
        text-align: left;
        vertical-align: top;
    }

    .invoice-table thead tr th {
        background-color: #6c757d !important;
        color: #ffffff !important;
        font-weight: 700;
    }

    .invoice-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .customer-phone {
        font-size: 22px;
        font-weight: 700;
        margin: 3px 0;
    }

    .product-name {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 2px;
    }

    .quantity-text {
        font-size: 22px;
        font-weight: 700;
        margin: 0;
    }

    .order-note {
        font-size: 16px;
        font-weight: 700;
        margin-top: 5px;
        margin-bottom: 0;
    }

    hr {
        border-top: 1px dashed red;
        margin: 12px 0;
    }

    @media print {
        @page {
            size: A4;
            margin: 8mm;
        }

        body {
            background: #ffffff !important;
        }

        body * {
            visibility: hidden;
        }

        #invoiceArea,
        #invoiceArea * {
            visibility: visible;
        }

        #invoiceArea {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
        }

        .invoice-section {
            width: 100%;
            background: #ffffff !important;
        }

        .invoice-table thead tr th {
            background-color: #6c757d !important;
            color: #ffffff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .content-wrapper,
        .content,
        .container-fluid {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }

        .main-footer,
        .main-header,
        .main-sidebar,
        .content-header,
        .btn,
        .no-print {
            display: none !important;
        }
    }
</style>
@endsection