@extends('adminlte::page')

@section('title', $title ?? 'Selected Invoices')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap no-print">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Selected Invoices' }}</h1>
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
    $orders = collect($orders ?? []);
    $selectedOrderIds = collect($selectedOrderIds ?? $orders->pluck('id'))->filter()->map(fn ($id) => (int) $id)->unique()->values();
    $invoicePrintTime = now()->timezone(config('app.order_display_timezone', 'Asia/Dhaka'));

    $logoUrl = null;

    if (!empty($siteSetting)) {
        if (!empty($siteSetting->logo)) {
            $logoUrl = $siteSetting->logo;
        } elseif (method_exists($siteSetting, 'getFirstMediaUrl')) {
            $logoUrl = $siteSetting->getFirstMediaUrl('site_logo') ?: null;
        }
    }

    $siteName = $siteSetting->website_name
        ?? $siteSetting->site_name
        ?? $siteSetting->title
        ?? config('app.name', 'Store');

    $siteAddress = $siteSetting->address ?? null;
    $sitePhone = $siteSetting->phone ?? null;
@endphp

<div class="alert alert-warning no-print mb-3">
    <i class="fas fa-exclamation-triangle mr-1"></i>
    Print successful না হওয়া পর্যন্ত <strong>Yes, Invoiced Printed!</strong> confirm করবেন না।
    Print dialog close হওয়ার পরে popup আসবে। <strong>No / Cancel</strong> দিলে invoice গুলো <strong>Pending Invoice</strong> list-এই থাকবে।
</div>

<div class="invoice-print-area">
    @forelse($orders->chunk(3) as $invoicePageOrders)
        <div class="invoice-page invoice-page-count-{{ $invoicePageOrders->count() }}">
            @foreach($invoicePageOrders as $order)
                @php
                    $orderCreatedAt = null;

                    if (method_exists($order, 'localDateTime')) {
                        $orderCreatedAt = $order->localDateTime('created_at');
                    } elseif (!empty($order->created_at)) {
                        $orderCreatedAt = $order->created_at->copy()->timezone(config('app.order_display_timezone', 'Asia/Dhaka'));
                    }

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

                    $courierMerchantId = $order->courierAccount?->merchant_id
                        ?? $order->courier?->merchant_id
                        ?? null;

                    $courierPhoneNumber = $order->courierAccount?->phone
                        ?? $order->courierAccount?->phone_number
                        ?? $order->courier?->phone_number
                        ?? null;

                    $subtotal = (float) ($order->sub_total ?? $order->items->sum('total_price'));
                    $shipping = (float) ($order->shipping_charge ?? 0);
                    $codCharge = (float) ($order->cod_charge ?? 0);
                    $grandTotal = (float) ($order->total_amount ?? ($subtotal + $shipping + $codCharge));
                @endphp

                <div class="invoice-slot" style="margin-top: 150px;">
                    <div class="invoice-box">
                        <table class="invoice-main-table invoice-header-table">
                            <tr>
                                <td style="width: 34%;">
                                    @if($logoUrl)
                                        <img src="{{ $logoUrl }}" class="invoice-logo" alt="{{ $siteName }}">
                                    @endif

                                    <strong>{{ $siteName }}</strong>

                                    @if($siteAddress)
                                        <br>{{ $siteAddress }}
                                    @endif

                                    @if($sitePhone)
                                        <br><strong>Phone:</strong> {{ $sitePhone }}
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
                                    <strong>Order Date:</strong> {{ $orderCreatedAt ? $orderCreatedAt->format('d M, Y h:i A') : '-' }}<br>
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
                                        $discountAmount = (float) ($item->discount_amount ?? 0);
                                        $lineTotal = (float) ($item->total_price ?? (($unitPrice * $qty) - $discountAmount));
                                        $lineTotal = max(0, $lineTotal);
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

                                            @if($discountAmount > 0)
                                                <br><small>Discount: {{ number_format($discountAmount) }} Tk</small>
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

                        <table class="invoice-main-table invoice-note-table">
                            <tr>
                                <td>
                                    <strong>Customer Note:</strong>
                                    {{ filled($order->customer_note) ? $order->customer_note : '-' }}
                                </td>
                            </tr>
                        </table>

                        <div class="footer-note">
                            Generated by {{ config('app.name') }} | Invoice Time <span class="invoice-print-time">{{ $invoicePrintTime->format('d M, Y h:i A') }}</span>
                        </div>
                    </div>
                </div>
            @endforeach

            @for($emptySlot = $invoicePageOrders->count(); $emptySlot < 3; $emptySlot++)
                <div class="invoice-slot invoice-slot-empty"></div>
            @endfor
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
    width: 100%;
    background: #fff;
}

.invoice-page {
    width: min(100%, 196mm);
    height: 283mm;
    min-height: 283mm;
    margin: 0 auto;
    padding: 0;
    background: #fff;
    display: flex;
    flex-direction: column;
    justify-content: space-evenly;
    align-items: stretch;
    page-break-after: always;
    break-after: page;
}

.invoice-page:last-child {
    page-break-after: auto;
    break-after: auto;
}

.invoice-page-count-1 {
    justify-content: flex-start;
}

.invoice-page-count-1 .invoice-slot {
    margin-top: 0;
}

.invoice-page-count-2 {
    justify-content: space-evenly;
}

.invoice-slot {
    width: 100%;
    margin-top: 0;
    max-width: none;
    min-width: 0;
    min-height: 0;
    flex: 0 0 auto;
    overflow: hidden;
    background: #fff;
    color: #000;
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 9.5px;
    display: block;
}

.invoice-slot-empty {
    display: none;
}

.invoice-box {
    width: 100%;
    max-width: none;
    min-width: 0;
    position: relative;
    display: block;
    max-height: 100%;
    overflow: hidden;
}

.invoice-main-table {
    width: 100%;
    max-width: none;
    min-width: 100%;
    border-collapse: collapse;
    margin: 0;
    table-layout: fixed;
}

.invoice-main-table,
.invoice-main-table th,
.invoice-main-table td {
    border: 1px solid #6c757d;
}

.invoice-main-table th,
.invoice-main-table td {
    padding: 2px 3px;
    vertical-align: top;
    text-align: left;
    line-height: 1.18;
    word-break: break-word;
}

.invoice-main-table th {
    background: #6c757d;
    color: #fff;
    font-weight: bold;
}

.invoice-header-table {
    border-bottom: 0;
}

.invoice-product-table {
    border-top: 0;
}

.invoice-note-table {
    margin-top: 0;
    border-top: 0;
}

.invoice-note-table td {
    height: 8mm;
    min-height: 8mm;
    vertical-align: middle;
}

.invoice-logo {
    max-height: 24px;
    max-width: 80px;
    display: block;
    margin-bottom: 1px;
}

.invoice-title {
    font-size: 11.5px;
    font-weight: bold;
}

.customer-phone {
    font-size: 13px;
    font-weight: bold;
}

.product-name {
    font-size: 10.5px;
    font-weight: bold;
}

.merchant-box {
    margin-top: 2px;
    font-size: 9px;
    font-weight: bold;
    line-height: 1.2;
}

.footer-note {
    width: 100%;
    text-align: center;
    font-size: 7.8px;
    color: #555;
    margin-top: 1.5mm;
    padding-top: 0;
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
        margin: 7mm;
    }

    .no-print,
    .main-sidebar,
    .main-header,
    .content-header,
    .main-footer {
        display: none !important;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    body,
    .wrapper,
    .content-wrapper,
    .content,
    .content .container-fluid,
    .container-fluid,
    .invoice-print-area {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }

    .content-wrapper {
        margin-left: 0 !important;
        min-height: auto !important;
    }

    .invoice-print-area {
        min-height: 283mm !important;
    }

    .invoice-page {
        width: 100% !important;
        max-width: none !important;
        height: 283mm !important;
        min-height: 283mm !important;
        margin: 0 !important;
        padding: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-evenly !important;
        align-items: stretch !important;
        page-break-after: always !important;
        break-after: page !important;
    }

    .invoice-page:last-child {
        page-break-after: auto !important;
        break-after: auto !important;
    }

    .invoice-page-count-1 {
        justify-content: flex-start !important;
        align-items: stretch !important;
    }

    .invoice-page-count-1 .invoice-slot {
        margin-top: 0 !important;
    }

    .invoice-page-count-2 {
        justify-content: space-evenly !important;
    }

    .invoice-slot {
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        min-height: 0 !important;
        flex: 0 0 auto !important;
        overflow: hidden !important;
        display: block !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .invoice-slot-empty {
        display: none !important;
    }

    .invoice-box {
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        max-height: 100% !important;
        overflow: hidden !important;
        position: relative !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .invoice-main-table {
        width: 100% !important;
        max-width: none !important;
        min-width: 100% !important;
        table-layout: fixed !important;
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
    const selectedInvoiceIds = @json($selectedOrderIds->all());
    let printStartedFromButton = false;
    let confirmPopupOpen = false;
    let confirmRequestRunning = false;
    let afterPrintHandled = false;

    function swalConfirmed(result) {
        return result && (result.value === true || result.isConfirmed === true);
    }

    function padNumber(number) {
        return String(number).padStart(2, '0');
    }

    function updateInvoicePrintTime() {
        const date = new Date();
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const day = padNumber(date.getDate());
        const month = monthNames[date.getMonth()];
        const year = date.getFullYear();
        let hours = date.getHours();
        const minutes = padNumber(date.getMinutes());
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;

        $('.invoice-print-time').text(day + ' ' + month + ', ' + year + ' ' + padNumber(hours) + ':' + minutes + ' ' + ampm);
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
                        Swal.fire({
                            title: 'Done',
                            text: res.message || 'Invoices marked as printed successfully.',
                            icon: 'success',
                            type: 'success',
                            confirmButtonText: 'OK'
                        }).then(function () {
                            window.location.href = "{{ route('admin.orders.invoices.complete') }}";
                        });
                    } else {
                        Swal.fire('Error', res.message || 'Failed to update invoice status.', 'error');
                    }
                },
                error: function (xhr) {
                    confirmRequestRunning = false;

                    Swal.fire(
                        'Error',
                        xhr.responseJSON?.message || 'Failed to update invoice status.',
                        'error'
                    );
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
        }, 500);
    }

    $('#btnPrintInvoices').on('click', function () {
        updateInvoicePrintTime();
        afterPrintHandled = false;
        printStartedFromButton = true;

        setTimeout(function () {
            window.print();
        }, 150);
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

