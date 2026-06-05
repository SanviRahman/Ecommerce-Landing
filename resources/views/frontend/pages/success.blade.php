@extends('frontend.layouts.master')

@php
    $siteSetting = $siteSetting ?? \App\Models\SiteSetting::query()
        ->where('status', true)
        ->latest()
        ->first();

    $websiteName = $siteSetting->website_name ?? config('app.name', 'Laravel');

    $noImage = asset('frontend/images/no-image.svg');

    try {
        $order->loadMissing(['items.product']);
    } catch (\Throwable $e) {
        //
    }

    $items = $order->items ?? collect();

    $imageOf = function ($model, $fallback = null) use ($noImage) {
        $fallback = $fallback ?: $noImage;

        if (! $model) {
            return $fallback;
        }

        foreach ([
            'image_url',
            'thumbnail_url',
            'featured_image_url',
            'primary_image_url',
            'main_image_url',
            'thumbnail',
            'featured_image',
            'main_image',
            'image',
            'photo',
        ] as $attribute) {
            try {
                if (! empty($model->{$attribute})) {
                    $value = $model->{$attribute};

                    if (\Illuminate\Support\Str::startsWith($value, ['http://', 'https://'])) {
                        return $value;
                    }

                    $value = ltrim($value, '/');

                    if (\Illuminate\Support\Str::startsWith($value, ['storage/'])) {
                        return asset($value);
                    }

                    return \Illuminate\Support\Facades\Storage::url($value);
                }
            } catch (\Throwable $e) {
                //
            }
        }

        if (method_exists($model, 'getFirstMediaUrl')) {
            foreach ([
                'product_images',
                'product_gallery',
                'gallery',
                'images',
                'image',
                'product_image',
                'thumbnail',
                'main_image',
                'featured_image',
                'default',
            ] as $collection) {
                try {
                    $url = $model->getFirstMediaUrl($collection);

                    if (! empty($url)) {
                        return $url;
                    }
                } catch (\Throwable $e) {
                    //
                }
            }
        }

        return $fallback;
    };

    $invoiceId = $order->invoice_id
        ?? $order->invoice_no
        ?? $order->invoice_number
        ?? ('ORDER-' . $order->id);

    $shippingCharge = (float) (
        $order->shipping_charge
        ?? $order->delivery_charge
        ?? 0
    );

    $codCharge = (float) (
        $order->cod_charge
        ?? 0
    );

    $subTotal = (float) (
        $order->sub_total
        ?? $order->subtotal
        ?? $items->sum(function ($item) {
            $price = (float) ($item->unit_price ?? $item->price ?? 0);
            $quantity = (int) ($item->quantity ?? 1);

            return $price * $quantity;
        })
    );

    $totalAmount = (float) (
        $order->total_amount
        ?? $order->grand_total
        ?? ($subTotal + $shippingCharge + $codCharge)
    );

    $isFreeDelivery = (bool) (
        $order->is_free_delivery
        ?? ($shippingCharge <= 0)
    );

    $rawDeliveryArea = trim((string) ($order->delivery_area ?? ''));

    $deliveryAreaLabel = match ($rawDeliveryArea) {
        'inside_dhaka'  => 'ঢাকার ভিতরে',
        'outside_dhaka' => 'ঢাকার বাইরে',
        'free_delivery' => 'ফ্রি ডেলিভারি',
        default         => $rawDeliveryArea,
    };

    if ($isFreeDelivery) {
        $deliveryAreaLabel = 'ফ্রি ডেলিভারি';
    }

    if ($deliveryAreaLabel === '') {
        $deliveryAreaLabel = '-';
    }

    $purchaseItems = $items->map(function ($item) {
        $product = $item->product ?? null;

        $productId = $item->product_id
            ?? $product?->id
            ?? '';

        $productName = $product?->name
            ?? $item->product_name
            ?? $item->name
            ?? 'Product';

        $unitPrice = (float) (
            $item->unit_price
            ?? $item->price
            ?? 0
        );

        $quantity = (int) ($item->quantity ?? 1);

        return [
            'item_id' => (string) $productId,
            'item_name' => (string) $productName,
            'price' => $unitPrice,
            'quantity' => $quantity,
        ];
    })->values();

    $purchasePayload = [
        'transaction_id' => (string) $invoiceId,
        'affiliation' => $websiteName . ' Online Store',
        'currency' => 'BDT',
        'value' => $totalAmount,
        'shipping' => $isFreeDelivery ? 0 : $shippingCharge,
        'tax' => $codCharge,
        'items' => $purchaseItems,
    ];
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

        .success-item {
            align-items: flex-start;
        }

        .success-item img {
            width: 68px;
            height: 68px;
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
                Invoice # {{ $invoiceId }}
            </div>

            @foreach($items as $item)
                @php
                    $product = $item->product ?? null;

                    $productName = $product?->name
                        ?? $item->product_name
                        ?? $item->name
                        ?? 'Product';

                    $unitPrice = (float) (
                        $item->unit_price
                        ?? $item->price
                        ?? 0
                    );

                    $quantity = (int) ($item->quantity ?? 1);

                    $lineTotal = (float) (
                        $item->total_price
                        ?? $item->line_total
                        ?? ($unitPrice * $quantity)
                    );

                    $productImage = $imageOf($product);
                @endphp

                <div class="success-item">
                    <img src="{{ $productImage }}"
                         alt="{{ $productName }}"
                         onerror="this.onerror=null;this.src='{{ $noImage }}';">

                    <div class="flex-grow-1">
                        <h5>{{ $productName }}</h5>
                        <span>
                            ৳{{ number_format($unitPrice) }}
                            x {{ $quantity }}
                        </span>
                    </div>

                    <strong>
                        ৳{{ number_format($lineTotal) }}
                    </strong>
                </div>
            @endforeach

            <div class="success-line">
                <span>SUBTOTAL</span>
                <strong>{{ number_format($subTotal, 2) }} tk</strong>
            </div>

            <div class="success-line">
                <span>DELIVERY</span>
                <strong>
                    @if($isFreeDelivery)
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

                        <div>{{ $deliveryAreaLabel }}</div>
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
    const purchasePayload = @json($purchasePayload);
    let retryCount = 0;
    const maxRetry = 20;

    function firePurchase() {
        if (!window.SFATracking || typeof window.SFATracking.purchase !== 'function') {
            retryCount++;

            if (retryCount <= maxRetry) {
                setTimeout(firePurchase, 300);
            }

            return;
        }

        window.SFATracking.purchase(purchasePayload);
    }

    firePurchase();
});
</script>
@endpush