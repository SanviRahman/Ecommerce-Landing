@extends('frontend.layouts.master')

@php
    $siteSetting = $siteSetting ?? \App\Models\SiteSetting::query()
        ->where('status', true)
        ->latest()
        ->first();

    $websiteName = $siteSetting->website_name ?? config('app.name', 'Laravel');

    $noImage = asset('frontend/images/no-image.svg');

    $items = $order->items ?? collect();

    $purchaseItems = $items->map(function ($item) {
        return [
            'item_id' => (string) ($item->product_id ?? ''),
            'item_name' => $item->product_name ?? 'Product',
            'price' => (float) ($item->unit_price ?? 0),
            'quantity' => (int) ($item->quantity ?? 1),
        ];
    })->values();

    $shippingCharge = (float) ($order->shipping_charge ?? 0);
    $codCharge = (float) ($order->cod_charge ?? 0);
    $totalAmount = (float) ($order->total_amount ?? 0);
@endphp

@section('title', 'Order Successful - ' . $websiteName)
@section('meta_description', 'আপনার অর্ডার সফলভাবে গ্রহণ করা হয়েছে।')

@push('css')
<style>
    .success-page {
        min-height: 100vh;
        padding: 70px 0;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
    }

    .success-card {
        max-width: 760px;
        margin: 0 auto;
        background: #ffffff;
        border: 1px solid #eef2f7;
        border-radius: 24px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.08);
        padding: 42px;
    }

    .success-icon {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #22c55e;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        margin: 0 auto 18px;
    }

    .success-title {
        color: #22c55e;
        font-size: 38px;
        font-weight: 900;
        text-align: center;
        margin-bottom: 10px;
    }

    .success-subtitle {
        color: #64748b;
        text-align: center;
        font-size: 17px;
        line-height: 1.8;
        margin-bottom: 30px;
    }

    .invoice-text {
        text-align: center;
        font-size: 18px;
        font-weight: 800;
        margin-bottom: 28px;
        color: #334155;
    }

    .success-item {
        display: flex;
        align-items: center;
        gap: 14px;
        border-top: 1px dashed #cbd5e1;
        padding: 16px 0;
    }

    .success-item img {
        width: 82px;
        height: 82px;
        object-fit: cover;
        border-radius: 14px;
        background: #e2e8f0;
    }

    .success-item h5 {
        color: #334155;
        font-size: 17px;
        font-weight: 900;
        margin-bottom: 4px;
    }

    .success-line {
        display: flex;
        justify-content: space-between;
        border-top: 1px dashed #cbd5e1;
        padding: 14px 0;
        color: #64748b;
        font-weight: 700;
    }

    .success-line strong {
        color: #16a34a;
    }

    .success-total {
        font-size: 20px;
        font-weight: 900;
    }

    .success-address {
        margin-top: 28px;
        border-top: 1px solid #eef2f7;
        padding-top: 24px;
        color: #64748b;
    }

    .success-address h5 {
        color: #334155;
        font-weight: 900;
        margin-bottom: 10px;
    }

    @media (max-width: 575px) {
        .success-card {
            padding: 28px 20px;
            border-radius: 18px;
        }

        .success-title {
            font-size: 30px;
        }
    }
</style>
@endpush

@section('content')
<section class="success-page">
    <div class="container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>

            <h1 class="success-title">অর্ডার সফল হয়েছে</h1>

            <p class="success-subtitle">
                আপনার অর্ডারের জন্য ধন্যবাদ। আমাদের প্রতিনিধি খুব শীঘ্রই আপনার সাথে যোগাযোগ করবে।
            </p>

            <div class="invoice-text">
                Invoice # {{ $order->invoice_id }}
            </div>

            @foreach($items as $item)
                @php
                    $productImage = $noImage;

                    try {
                        if ($item->product && ! empty($item->product->thumbnail)) {
                            $productImage = $item->product->thumbnail;
                        }
                    } catch (\Throwable $e) {
                        $productImage = $noImage;
                    }
                @endphp

                <div class="success-item">
                    <img src="{{ $productImage }}"
                         alt="{{ $item->product_name }}"
                         onerror="this.onerror=null;this.src='{{ $noImage }}';">

                    <div class="flex-grow-1">
                        <h5>{{ $item->product_name }}</h5>
                        <span>
                            ৳{{ number_format($item->unit_price ?? 0) }}
                            x {{ $item->quantity }}
                        </span>
                    </div>

                    <strong>
                        ৳{{ number_format($item->total_price ?? 0) }}
                    </strong>
                </div>
            @endforeach

            <div class="success-line">
                <span>SUBTOTAL</span>
                <strong>{{ number_format($order->sub_total ?? 0, 2) }} tk</strong>
            </div>

            <div class="success-line">
                <span>DELIVERY</span>
                <strong>
                    @if($order->is_free_delivery)
                        Free Delivery
                    @else
                        {{ number_format($shippingCharge, 2) }} tk
                    @endif
                </strong>
            </div>

            <div class="success-line">
                <span>COD CHARGE</span>
                <strong>{{ number_format($codCharge, 2) }} tk</strong>
            </div>

            <div class="success-line success-total">
                <span>TOTAL</span>
                <strong>{{ number_format($totalAmount, 2) }} tk</strong>
            </div>

            <div class="success-address">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h5>Address</h5>
                        <div>{{ $order->customer_name }}</div>
                        <div>{{ $order->address }}</div>
                        <div>{{ $order->phone }}</div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <h5>Delivery</h5>
                        <div>ক্যাশ অন ডেলিভারি</div>

                        @if($order->is_free_delivery)
                            <div>ফ্রি ডেলিভারি</div>
                        @else
                            <div>
                                {{ $order->delivery_area === 'inside_dhaka' ? 'ঢাকার ভিতরে' : 'ঢাকার বাইরে' }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.SFATracking) {
        window.SFATracking.purchase({
            transaction_id: @json($order->invoice_id),
            affiliation: @json($websiteName . ' Online Store'),
            currency: 'BDT',
            value: Number(@json($totalAmount)),
            shipping: Number(@json($shippingCharge)),
            tax: Number(@json($codCharge)),
            items: @json($purchaseItems)
        });
    }
});
</script>
@endpush