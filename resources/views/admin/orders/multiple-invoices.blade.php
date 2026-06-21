@extends('adminlte::page')

@section('title', $title ?? 'Selected Invoices')

@section('content_header')
<div class="d-flex justify-content-between align-items-center flex-wrap no-print">
    <div>
        <h1 class="mb-0">{{ $title ?? 'Selected Invoices' }}</h1>
        <small class="text-muted">Print selected invoices, then confirm only after printing is completed.</small>
    </div>

    <div class="mt-2 mt-md-0 d-flex gap-2">
        <!-- লেআউট পরিবর্তন করার সুইচ বাটন -->
        <button type="button" class="btn btn-info text-white mr-2" id="btnToggleLayout">
            <i class="fas fa-th-large mr-1"></i> Switch to 8-Invoice Grid
        </button>

        <button type="button" class="btn btn-primary mr-2" id="btnPrintInvoices">
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

<!-- ফিক্সড এবং ছোট ফন্ট সাইজের অ্যালার্ট বক্স -->
<div class="alert alert-warning no-print mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div style="flex: 1; min-width: 250px; font-size: 11.5px; color: #555; line-height: 1.4;">
            <i class="fas fa-exclamation-triangle mr-1 text-warning" style="font-size: 13px;"></i>
            Print successful না হওয়া পর্যন্ত <strong>Yes, Invoiced Printed!</strong> confirm করবেন না।
            Print dialog close হওয়ার পরে popup আসবে। <strong>No / Cancel</strong> দিলে invoice গুলো <strong>Pending Invoice</strong> list-এই থাকবে।
        </div>
        <div class="mt-2 mt-md-0 font-weight-bold">
            চলতি লেআউট: <span id="lblCurrentLayout" class="badge badge-secondary px-2 py-1" style="font-size: 10.5px;">4 Invoices (Full Width)</span>
        </div>
    </div>
</div>

<!-- মূল কন্টেইনার -->
<div id="invoiceContainer" class="layout-4-full">
    @forelse($orders as $order)
        @php
            $orderCreatedAt = null;

            if (method_exists($order, 'localDateTime')) {
                $orderCreatedAt = $order->localDateTime('created_at');
            } elseif (!empty($order->created_at)) {
                $orderCreatedAt = $order->created_at->copy()->timezone(config('app.order_display_timezone', 'Asia/Dhaka'));
            }

            $deliveryArea = $order->delivery_area ? ucwords(str_replace('_', ' ', $order->delivery_area)) : '-';
            $paymentStatus = $order->payment_status ? ucwords(str_replace('_', ' ', $order->payment_status)) : '-';
            $orderStatus = $order->order_status ? ucwords(str_replace('_', ' ', $order->order_status)) : '-';

            $courierName = $order->courier?->name ?? ($courierServices[$order->courier_service] ?? 'Not Selected');
            $courierMerchantId = $order->courierAccount?->merchant_id ?? $order->courier?->merchant_id ?? null;
            $courierPhoneNumber = $order->courierAccount?->phone ?? $order->courierAccount?->phone_number ?? $order->courier?->phone_number ?? null;

            $subtotal = (float) ($order->sub_total ?? $order->items->sum('total_price'));
            $shipping = (float) ($order->shipping_charge ?? 0);
            $codCharge = (float) ($order->cod_charge ?? 0);
            $grandTotal = (float) ($order->total_amount ?? ($subtotal + $shipping + $codCharge));
            
            // CID / Tracking value resolver for Pathao + SteadFast.
            // Pathao saves CID in pathao_consignment_id.
            // SteadFast saves CID/tracking in steadfast_consignment_id / steadfast_tracking_code.
            $courierCode = strtolower((string) (
                $order->courier_service
                ?? $order->courier?->code
                ?? $order->courierAccount?->code
                ?? ''
            ));

            if ($courierCode === 'steadfast') {
                $cidValue = collect([
                    $order->steadfast_consignment_id ?? null,
                    $order->steadfast_tracking_code ?? null,
                    $order->consignment_id ?? null,
                ])->filter(fn ($value) => filled($value))->first() ?: '-';
            } elseif ($courierCode === 'pathao') {
                $cidValue = collect([
                    $order->pathao_consignment_id ?? null,
                    $order->consignment_id ?? null,
                ])->filter(fn ($value) => filled($value))->first() ?: '-';
            } else {
                $cidValue = collect([
                    $order->pathao_consignment_id ?? null,
                    $order->steadfast_consignment_id ?? null,
                    $order->steadfast_tracking_code ?? null,
                    $order->consignment_id ?? null,
                ])->filter(fn ($value) => filled($value))->first() ?: '-';
            }
        @endphp

        <!-- ইনভয়েস স্লট কন্টেইনার -->
        <div class="invoice-wrapper-slot">
            
            <!-- ========================================== -->
            <!-- লেআউট ১: ৪টি ইনভয়েস স্টাইল (Full Width View) -->
            <!-- ========================================== -->
            <div class="view-layout-4">
                <table class="invoice-main-table" style="border-bottom: 0;">
                    <tr>
                        <td style="width: 34%;">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" class="invoice-logo" alt="{{ $siteName }}">
                            @endif
                            <strong>{{ $siteName }}</strong>
                            @if($siteAddress)<br>{{ $siteAddress }}@endif
                            @if($sitePhone)<br><strong>Phone:</strong> {{ $sitePhone }}@endif

                            @if($courierName || $courierMerchantId || $courierPhoneNumber)
                                <div class="merchant-box-4">
                                    @if($courierName && $courierName !== 'Not Selected') Courier Name: {{ $courierName }}<br>@endif
                                    @if($courierMerchantId) Merchant ID: {{ $courierMerchantId }}<br>@endif
                                    @if($courierPhoneNumber) Courier Phone: {{ $courierPhoneNumber }}@endif
                                </div>
                            @endif
                        </td>
                        <td style="width: 33%;">
                            <div class="invoice-title-4">CUSTOMER INFO</div>
                            <strong>{{ $order->customer_name }}</strong><br>
                            <div class="customer-phone-4">{{ $order->phone }}</div>
                            {{ $order->address }}<br>
                            <strong>Area:</strong> {{ $deliveryArea }}
                        </td>
                        <td style="width: 33%;">
                            <div class="invoice-title-4">CID: {{ $cidValue }}</div>
                            <strong>Invoice:</strong> {{ $order->invoice_id }}<br>
                            <strong>Order Date:</strong> {{ $orderCreatedAt ? $orderCreatedAt->format('d M, Y h:i A') : '-' }}<br>
                            <strong>Status:</strong> {{ $orderStatus }}<br>
                            <strong>Courier:</strong> {{ $courierName }}
                        </td>
                    </tr>
                </table>

                <table class="invoice-main-table invoice-product-table-4" style="border-top: 0;">
                    <thead>
                        <tr>
                            <th style="width: 60%;">Product</th>
                            <th style="width: 15%;">Qty</th>
                            <th style="width: 25%;">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td><div class="product-name-4">{{ $item->product_name ?: ($item->product->name ?? 'Product') }}</div></td>
                                <td class="qty-4">{{ (int)($item->quantity ?? 1) }}</td>
                                <td>{{ number_format((float)($item->total_price ?? 0)) }} Tk</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td class="border-none"></td><th>Sub Total:</th><td>{{ number_format($subtotal) }} Tk</td></tr>
                        <tr><td class="border-none"></td><th>Delivery:</th><td>{{ !empty($order->is_free_delivery) ? 'Free' : number_format($shipping).' Tk' }}</td></tr>
                        @if($codCharge > 0)
                            <tr><td class="border-none"></td><th>COD:</th><td>{{ number_format($codCharge) }} Tk</td></tr>
                        @endif
                        <tr><td class="border-none"></td><th>Total:</th><td><strong>{{ number_format($grandTotal) }} Tk</strong></td></tr>
                    </tfoot>
                </table>

                <table class="invoice-main-table" style="border-top: 0;">
                    <tr>
                        <td style="position: relative; padding-bottom: 20px;">
                            <strong>Customer Note:</strong> {{ filled($order->customer_note) ? $order->customer_note : '-' }}
                            <div class="footer-note-inside-4">
                                Generated by {{ config('app.name') }}
                                | Assigned Employee: {{ $order->assignedEmployee?->name ?? 'Unassigned' }}
                                | Invoice Time <span class="invoice-print-time">{{ $invoicePrintTime->format('d M, Y h:i A') }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ========================================== -->
            <!-- লেআউট ২: ৮টি ইনভয়েস স্টাইল (Grid Mini View) -->
            <!-- ========================================== -->
            <div class="view-layout-8">
                <div class="invoice-card-header">
                    <div class="shop-info">
                        @if($logoUrl)<img src="{{ $logoUrl }}" class="invoice-logo-mini" alt="{{ $siteName }}">@endif
                        <span class="shop-name">{{ $siteName }}</span>
                    </div>
                    <!-- INV এর জায়গায় CID নিয়ে আসা হলো -->
                    <div class="invoice-number-badge">CID: {{ $cidValue }}</div>
                </div>

                <div class="invoice-meta-row">
                    <div class="meta-col customer-side">
                        <div class="section-label">CUSTOMER INFO</div>
                        <div class="cust-name">{{ $order->customer_name }}</div>
                        <div class="customer-phone-mini">{{ $order->phone }}</div>
                        <div class="cust-address">{{ $order->address }}</div>
                        <div><strong>Area:</strong> {{ $deliveryArea }}</div>
                    </div>
                    <div class="meta-col order-side">
                        <div class="section-label">ORDER DETAILS</div>
                        <!-- CID এর জায়গায় Invoice আইডি নিয়ে আসা হলো -->
                        <div><strong>Invoice:</strong> #{{ $order->invoice_id }}</div>
                        <div><strong>Date:</strong> {{ $orderCreatedAt ? $orderCreatedAt->format('d M, y h:i A') : '-' }}</div>
                        <div><strong>Courier:</strong> {{ $courierName }}</div>
                    </div>
                </div>

                <!-- ছোট ইনফো বক্স (কুরিয়ার, মার্চেন্ট আইডি এবং ফোন) -->
                @if($courierName || $courierMerchantId || $courierPhoneNumber)
                    <div class="merchant-box-8">
                        @if($courierName && $courierName !== 'Not Selected') <span><strong>Courier:</strong> {{ $courierName }}</span> | @endif
                        @if($courierMerchantId) <span><strong>MID:</strong> {{ $courierMerchantId }}</span> | @endif
                        @if($courierPhoneNumber) <span><strong>C.Phone:</strong> {{ $courierPhoneNumber }}</span> @endif
                    </div>
                @endif

                <table class="invoice-mini-table">
                    <thead>
                        <tr>
                            <th style="width: 55%;">Product</th>
                            <th style="width: 15%; text-align: center;">Qty</th>
                            <th style="width: 30%; text-align: right;">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td><div class="prod-name-mini">{{ $item->product_name ?: ($item->product->name ?? 'Product') }}</div></td>
                                <td class="qty-cell-mini">{{ (int)($item->quantity ?? 1) }}</td>
                                <td style="text-align: right;">{{ number_format((float)($item->total_price ?? 0)) }} ৳</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="invoice-card-footer">
                    <div class="footer-notes">
                        @if(filled($order->customer_note))
                            <div class="cust-note-box"><strong>Note:</strong> {{ $order->customer_note }}</div>
                        @endif
                        <div class="time-stamp">Time: {{ $invoicePrintTime->format('d M, y h:i A') }}</div>
                    </div>
                    <div class="pricing-summary">
                        <div class="price-line">Sub: <span>{{ number_format($subtotal) }} ৳</span></div>
                        <div class="price-line">Del: <span>{{ !empty($order->is_free_delivery) ? 'Free' : number_format($shipping) }}</span></div>
                        <div class="price-line total-highlight">Total: <span>{{ number_format($grandTotal) }} ৳</span></div>
                    </div>
                </div>
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
* { box-sizing: border-box; }

.invoice-wrapper-slot { background: #fff; color: #000; page-break-inside: avoid; break-inside: avoid; }
.border-none { border: none !important; }
.text-center { text-align: center !important; }

/* লেআউট ৪ স্টাইল */
.layout-4-full { width: 100%; max-width: 196mm; margin: 0 auto; display: flex; flex-direction: column; gap: 15px; }
.layout-4-full .invoice-wrapper-slot { border: 1px dashed #ddd; padding: 10px; }
.layout-4-full .view-layout-8 { display: none; }
.layout-4-full .view-layout-4 { display: block; }

.invoice-main-table { width: 100%; border-collapse: collapse; margin: 0; table-layout: fixed; }
.invoice-main-table, .invoice-main-table th, .invoice-main-table td { border: 1px solid #495057; }
.invoice-main-table th, .invoice-main-table td { padding: 5px 6px; vertical-align: top; text-align: left; line-height: 1.3; word-break: break-word; font-family: sans-serif; font-size: 12px; }
.invoice-main-table th { background: #dddddd; color: #000000; font-weight: bold; }
.invoice-logo { max-height: 35px; max-width: 100px; display: block; margin-bottom: 4px; }
.invoice-title-4 { font-size: 13px; font-weight: bold; border-bottom: 1px solid #ddd; padding-bottom: 2px; margin-bottom: 4px; }
.customer-phone-4 { font-size: 15px; font-weight: bold; }
.product-name-4 { font-size: 13px; font-weight: bold; }
.qty-4 { font-size: 16px; font-weight: bold; text-align: center; }
.merchant-box-4 { margin-top: 4px; font-size: 11px; font-weight: bold; }
.footer-note-inside-4 { position: absolute; bottom: 2px; left: 5px; right: 5px; text-align: center; font-size: 10px; color: #444; border-top: 1px dotted #ccc; padding-top: 2px; }

/* লেআউট ৮ স্টাইল */
.layout-8-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; width: 100%; max-width: 196mm; margin: 0 auto; }
.layout-8-grid .invoice-wrapper-slot { border: 1px dashed #bbb; padding: 8px; display: flex; flex-direction: column; justify-content: space-between; min-height: 66mm; }
.layout-8-grid .view-layout-4 { display: none; }
.layout-8-grid .view-layout-8 { display: flex; flex-direction: column; height: 100%; justify-content: space-between; font-size: 11px; font-family: Arial, sans-serif; }

.invoice-card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ddd; padding-bottom: 4px; margin-bottom: 5px; }
.shop-info { display: flex; align-items: center; gap: 5px; }
.invoice-logo-mini { max-height: 20px; max-width: 60px; }
.shop-name { font-weight: bold; font-size: 12px; }
.invoice-number-badge { background: #dddddd; padding: 2px 6px; font-weight: bold; border-radius: 3px; font-size: 11px; max-width: 65%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.invoice-meta-row { display: flex; gap: 8px; margin-bottom: 3px; }
.meta-col { flex: 1; line-height: 1.2; }
.customer-side { border-right: 1px dotted #ccc; padding-right: 4px; }
.section-label { font-size: 9px; color: #666; font-weight: bold; margin-bottom: 2px; }
.cust-name { font-weight: bold; font-size: 11px; }
.customer-phone-mini { font-size: 13px; font-weight: bold; }
.cust-address { font-size: 10px; color: #333; }

/* ২য় মডেলের কুরিয়ার সেকশনের স্টাইল */
.merchant-box-8 { font-size: 9px; background: #f1f3f5; padding: 2px 4px; border-radius: 3px; margin-bottom: 5px; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.invoice-mini-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
.invoice-mini-table th, .invoice-mini-table td { border: 1px solid #aaaaaa; padding: 3px 4px; vertical-align: top; font-size: 10.5px; }
.invoice-mini-table th { background: #dddddd; font-weight: bold; }
.prod-name-mini { font-weight: bold; font-size: 11px; }
.qty-cell-mini { font-size: 13px; font-weight: bold; text-align: center; }
.invoice-card-footer { display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #ddd; padding-top: 4px; margin-top: auto; }
.footer-notes { flex: 1; padding-right: 5px; }
.cust-note-box { font-size: 9.5px; background: #f9f9f9; padding: 2px; border-left: 2px solid #ccc; margin-bottom: 2px; }
.time-stamp { font-size: 8.5px; color: #666; }
.pricing-summary { width: 45%; font-size: 10px; line-height: 1.2; }
.price-line { display: flex; justify-content: space-between; }
.total-highlight { font-weight: bold; font-size: 11.5px; border-top: 1px solid #000; padding-top: 1px; margin-top: 1px; }

/* প্রিন্ট কোয়েরি */
@media print {
    @page { size: A4 portrait; margin: 8mm 6mm; }
    .no-print, .main-sidebar, .main-header, .content-header, .main-footer { display: none !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body, .wrapper, .content-wrapper, .content, .container-fluid { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; background: #fff !important; }
    .content-wrapper { margin-left: 0 !important; min-height: auto !important; }
    
    .layout-4-full { display: flex !important; flex-direction: column !important; gap: 0 !important; width: 100% !important; }
    .layout-4-full .invoice-wrapper-slot { border: 1px solid #495057 !important; margin-bottom: 10px !important; padding: 8px !important; width: 100% !important; }
    
    .layout-8-grid { display: grid !important; grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; width: 100% !important; }
    .layout-8-grid .invoice-wrapper-slot { border: 1px solid #888888 !important; padding: 8px !important; }

    .invoice-main-table th, .invoice-mini-table th { background: #dddddd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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

    // লেআউট সুইচ টগল কোড
    $('#btnToggleLayout').on('click', function() {
        const container = $('#invoiceContainer');
        const badge = $('#lblCurrentLayout');
        
        if (container.hasClass('layout-4-full')) {
            container.removeClass('layout-4-full').addClass('layout-8-grid');
            $(this).html('<i class="fas fa-th-list mr-1"></i> Switch to 4-Invoice Layout');
            $(this).removeClass('btn-info').addClass('btn-success');
            badge.text('8 Invoices (2 Column Grid)').removeClass('badge-secondary').addClass('badge-success');
        } else {
            container.removeClass('layout-8-grid').addClass('layout-4-full');
            $(this).html('<i class="fas fa-th-large mr-1"></i> Switch to 8-Invoice Grid');
            $(this).removeClass('btn-success').addClass('btn-info');
            badge.text('4 Invoices (Full Width)').removeClass('badge-success').addClass('badge-secondary');
        }
    });

    function swalConfirmed(result) {
        return result && (result.value === true || result.isConfirmed === true);
    }

    function confirmPrintedPopup() {
        if (!selectedInvoiceIds.length) {
            Swal.fire('Notice', 'No selected invoice found.', 'info');
            return;
        }
        if (confirmPopupOpen || confirmRequestRunning) return;
        confirmPopupOpen = true;

        Swal.fire({
            title: 'Are you sure?',
            text: 'All invoice printed?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Invoiced Printed!',
            cancelButtonText: 'No / Cancel',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            allowOutsideClick: false
        }).then(function (result) {
            confirmPopupOpen = false;
            if (!swalConfirmed(result)) return;
            confirmRequestRunning = true;

            $.ajax({
                url: "{{ route('admin.orders.invoices.mark_printed') }}",
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', ids: selectedInvoiceIds },
                success: function (res) {
                    confirmRequestRunning = false;
                    if (res.status) {
                        Swal.fire('Done', res.message, 'success').then(function () {
                            window.location.href = "{{ route('admin.orders.invoices.complete') }}";
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function (xhr) {
                    confirmRequestRunning = false;
                    Swal.fire('Error', xhr.responseJSON?.message || 'Error occurred.', 'error');
                }
            });
        });
    }

    function openConfirmPopupAfterPrint() {
        if (!printStartedFromButton || afterPrintHandled) return;
        afterPrintHandled = true;
        printStartedFromButton = false;
        setTimeout(function () { confirmPrintedPopup(); }, 500);
    }

    $('#btnPrintInvoices').on('click', function () {
        afterPrintHandled = false;
        printStartedFromButton = true;
        setTimeout(function () { window.print(); }, 150);
    });

    if ('onafterprint' in window) {
        window.addEventListener('afterprint', openConfirmPopupAfterPrint);
    }
});
</script>
@endsection