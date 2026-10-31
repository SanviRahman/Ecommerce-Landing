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
    $deliveryAreaLabel = $isFreeDelivery
        ? 'ফ্রি ডেলিভারি'
        : $rawDeliveryArea;

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
<link rel="stylesheet" href="{{ asset('frontend/css/success.css') }}?v=20261024-frontend2">
@endpush

@section('content')
<section class="success-page">
    <div class="container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>

            <h1 class="success-title">অর্ডার সফল হয়েছে</h1>
            <p><a href="{{ $order->campaign?->public_url ?? route('home') }}">← পণ্যের পেজে ফিরে যান</a></p>

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
                         data-image-fallback="{{ $noImage }}">

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
<div id="sfaSuccessConfig" hidden data-config="{{ json_encode($purchasePayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"></div>
<script src="{{ asset('frontend/js/success.js') }}?v=20261024-frontend2" defer></script>
@endpush