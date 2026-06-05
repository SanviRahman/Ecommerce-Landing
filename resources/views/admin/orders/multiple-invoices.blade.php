@extends('adminlte::page')

@section('title', $title ?? 'Selected Invoices')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap no-print">
    <div>
        <h1 class="mb-0">Selected Invoices</h1>
        <small class="text-muted">Print selected invoices, then confirm only after printing is completed.</small>
    </div>

    <div class="mt-2 mt-md-0">
        <button type="button" class="btn btn-primary" id="btnPrintInvoices">
            <i class="fas fa-print mr-1"></i> Print Selected Invoices
        </button>

        <a href="{{ route('admin.orders.invoices.pending') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Pending Invoice
        </a>
    </div>
</div>
@endsection

@section('content')
@php
    $selectedOrderIds = collect($selectedOrderIds ?? $orders->pluck('id'))->values();

    $logoUrl = null;
    if (!empty($siteSetting) && method_exists($siteSetting, 'getFirstMedia') && $siteSetting->getFirstMedia('site_logo')) {
        $logoUrl = $siteSetting->logo ?? $siteSetting->getFirstMediaUrl('site_logo');
    }

    $siteName = $siteSetting->website_name
        ?? $siteSetting->title
        ?? config('app.name', 'Store');
@endphp

<div class="alert alert-warning no-print mb-3">
    <i class="fas fa-exclamation-triangle mr-1"></i>
    Print successful না হওয়া পর্যন্ত <strong>Yes, Invoiced Printed!</strong> confirm করবেন না।
    Print dialog close হওয়ার পরে popup আসবে। <strong>Cancel/No</strong> দিলে invoice গুলো <strong>Pending Invoice</strong> list-এই থাকবে।
</div>

<div class="invoice-print-area">
    @forelse($orders as $order)
        @php
            $orderCreatedAt = method_exists($order, 'localDateTime')
                ? $order->localDateTime('created_at')
                : ($order->created_at ? $order->created_at->copy()->timezone(config('app.order_display_timezone', 'Asia/Dhaka')) : null);

            $deliveryArea = $order->delivery_area
                ? ucwords(str_replace('_', ' ', $order->delivery_area))
                : '-';

            $paymentStatus = $order->payment_status
                ? ucwords(str_replace('_', ' ', $order->payment_status))
                : '-';

            $orderStatus = $order->order_status
                ? ucwords(str_replace('_', ' ', $order->order_status))
                : '-';

            $courierName = $order->courier?->name
                ?? ($courierServices[$order->courier_service] ?? 'Not Selected');

            $courierMerchantId = $order->courier?->merchant_id ?? null;
            $courierPhoneNumber = $order->courier?->phone_number ?? null;

            $subtotal = (float) ($order->sub_total ?? $order->items->sum('total_price'));
            $shipping = (float) ($order->shipping_charge ?? 0);
            $codCharge = (float) ($order->cod_charge ?? 0);
            $grandTotal = (float) ($order->total_amount ?? ($subtotal + $shipping + $codCharge));
        @endphp

        <div class="invoice-card">
            <table class="invoice-main-table">
                <tr>
                    <td style="width: 34%;">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" class="invoice-logo" alt="{{ $siteName }}">
                        @endif

                        <strong>{{ $siteName }}</strong>

                        @if(!empty($siteSetting?->address))
                            <br>{{ $siteSetting->address }}
                        @endif

                        @if(!empty($siteSetting?->phone))
                            <br><strong>Phone:</strong> {{ $siteSetting->phone }}
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

                    <td style="width: 33%;">
                        <div class="invoice-title">CUSTOMER INFO</div>
                        <strong>{{ $order->customer_name }}</strong><br>
                        <div class="customer-phone">{{ $order->phone }}</div>
                        {{ $order->address }}<br>
                        <strong>Area:</strong> {{ $deliveryArea }}
                    </td>

                    <td style="width: 33%;">
                        <div class="invoice-title">Invoice #{{ $order->invoice_id }}</div>
                        <strong>Date:</strong> {{ $orderCreatedAt ? $orderCreatedAt->format('d M, Y h:i A') : '-' }}<br>
                        <strong>Status:</strong> {{ $orderStatus }}<br>
                        <strong>Payment:</strong> {{ $paymentStatus }}<br>
                        <strong>Courier:</strong> {{ $courierName }}<br>
                        <strong>Employee:</strong> {{ $order->assignedEmployee->name ?? 'Unassigned' }}
                    </td>
                </tr>
            </table>

            <table class="invoice-main-table invoice-product-table">
                <thead>
                    <tr>
                        <th style="width: 60%;">Product</th>
                        <th style="width: 15%;">Qty</th>
                        <th style="width: 25%;">Price</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($order->items as $item)
                        @php
                            $itemName = $item->product_name ?: ($item->product->name ?? 'Product');
                            $qty = (int) ($item->quantity ?? 1);
                            $unitPrice = (float) ($item->unit_price ?? 0);
                            $lineTotal = (float) ($item->total_price ?? ($unitPrice * $qty));
                        @endphp
                        <tr>
                            <td>
                                <div class="product-name">{{ $itemName }}</div>
                            </td>
                            <td>{{ $qty }}</td>
                            <td>
                                {{ number_format($lineTotal) }} Tk
                                @if($unitPrice > 0)
                                    <br><small>Unit: {{ number_format($unitPrice) }} Tk</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center">No items found.</td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot>
                    <tr>
                        <td class="border-none"></td>
                        <th>Sub Total:</th>
                        <td>{{ number_format($subtotal) }} Tk</td>
                    </tr>
                    <tr>
                        <td class="border-none"></td>
                        <th>Delivery:</th>
                        <td>
                            @if(!empty($order->is_free_delivery))
                                Free Delivery
                            @else
                                {{ number_format($shipping) }} Tk
                            @endif
                        </td>
                    </tr>
                    @if($codCharge > 0)
                        <tr>
                            <td class="border-none"></td>
                            <th>COD Charge:</th>
                            <td>{{ number_format($codCharge) }} Tk</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="border-none"></td>
                        <th>Total:</th>
                        <td><strong>{{ number_format($grandTotal) }} Tk</strong></td>
                    </tr>
                </tfoot>
            </table>

            <div class="footer-note">
                Generated by {{ config('app.name') }} | Invoice #{{ $order->invoice_id }}
            </div>
        </div>
    @empty
        <div class="alert alert-info no-print">No invoices found.</div>
    @endforelse
</div>
@endsection

@section('plugins.Sweetalert2', true)

@section('css')
<style>
* {
    box-sizing: border-box;
}

.invoice-print-area {
    background: #fff;
}

.invoice-card {
    height: 92mm;
    overflow: hidden;
    padding: 5mm;
    border-bottom: 1px dashed #dc2626;
    page-break-inside: avoid;
    background: #fff;
    color: #000;
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10px;
}

.invoice-card:nth-child(3n) {
    page-break-after: always;
}

.invoice-main-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 4px;
}

.invoice-main-table,
.invoice-main-table th,
.invoice-main-table td {
    border: 1px solid #6c757d;
}

.invoice-main-table th,
.invoice-main-table td {
    padding: 3px;
    vertical-align: top;
    text-align: left;
}

.invoice-main-table th {
    background: #6c757d;
    color: #fff;
    font-weight: bold;
}

.invoice-logo {
    max-height: 28px;
    display: block;
    margin-bottom: 3px;
}

.invoice-title {
    font-size: 13px;
    font-weight: bold;
}

.customer-phone {
    font-size: 15px;
    font-weight: bold;
}

.product-name {
    font-size: 12px;
    font-weight: bold;
}

.merchant-box {
    margin-top: 3px;
    font-size: 10px;
    font-weight: bold;
    line-height: 1.35;
}

.footer-note {
    text-align: center;
    font-size: 9px;
    color: #555;
}

.border-none {
    border: none !important;
}

.text-center {
    text-align: center !important;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 8mm;
    }

    .no-print,
    .main-sidebar,
    .main-header,
    .content-header,
    .main-footer {
        display: none !important;
    }

    body,
    .content-wrapper,
    .content,
    .container-fluid,
    .invoice-print-area {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    .invoice-card {
        height: 92mm;
        padding: 3mm;
    }

    .invoice-main-table th {
        background: #6c757d !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
@endsection

@section('js')
<script>
$(function () {
    const selectedInvoiceIds = @json($selectedOrderIds);
    let printStartedFromButton = false;
    let confirmPopupOpen = false;
    let confirmRequestRunning = false;
    let afterPrintHandled = false;

    function swalConfirmed(result) {
        return result && (result.value === true || result.isConfirmed === true);
    }

    function confirmPrintedPopup() {
        if (!selectedInvoiceIds.length) {
            Swal.fire('Notice', 'No selected invoice found.', 'info');
            return;
        }

        if (confirmPopupOpen || confirmRequestRunning) {
            return;
        }

        confirmPopupOpen = true;

        Swal.fire({
            title: 'Are you sure?',
            text: 'All invoice printed?',
            icon: 'warning',
            type: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Invoiced Printed!',
            cancelButtonText: 'No / Cancel',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            allowOutsideClick: false,
            allowEscapeKey: true
        }).then(function (result) {
            confirmPopupOpen = false;

            if (!swalConfirmed(result)) {
                Swal.fire({
                    title: 'Pending kept',
                    text: 'Invoice গুলো Pending Invoice list-এই থাকবে।',
                    icon: 'info',
                    type: 'info',
                    timer: 1800,
                    showConfirmButton: false
                });
                return;
            }

            confirmRequestRunning = true;

            $.ajax({
                url: "{{ route('admin.orders.invoices.mark_printed') }}",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    ids: selectedInvoiceIds
                },
                success: function (res) {
                    confirmRequestRunning = false;

                    if (res.status) {
                        Swal.fire('Done', res.message || 'Invoices marked as printed.', 'success')
                            .then(function () {
                                window.location.href = "{{ route('admin.orders.invoices.complete') }}";
                            });
                    } else {
                        Swal.fire('Error', res.message || 'Failed to update invoice status.', 'error');
                    }
                },
                error: function (xhr) {
                    confirmRequestRunning = false;
                    Swal.fire('Error', xhr.responseJSON?.message || 'Failed to update invoice status.', 'error');
                }
            });
        });
    }

    function openConfirmPopupAfterPrint() {
        if (!printStartedFromButton || afterPrintHandled) {
            return;
        }

        afterPrintHandled = true;
        printStartedFromButton = false;

        setTimeout(function () {
            confirmPrintedPopup();
        }, 400);
    }

    $('#btnPrintInvoices').on('click', function () {
        afterPrintHandled = false;
        printStartedFromButton = true;
        window.print();
    });

    $('#btnConfirmPrinted').on('click', function () {
        confirmPrintedPopup();
    });

    if ('onafterprint' in window) {
        window.addEventListener('afterprint', openConfirmPopupAfterPrint);
    }

    if (window.matchMedia) {
        const mediaQueryList = window.matchMedia('print');
        const mediaQueryHandler = function (event) {
            if (!event.matches) {
                openConfirmPopupAfterPrint();
            }
        };

        if (mediaQueryList.addEventListener) {
            mediaQueryList.addEventListener('change', mediaQueryHandler);
        } else if (mediaQueryList.addListener) {
            mediaQueryList.addListener(mediaQueryHandler);
        }
    }
});
</script>
@endsection
