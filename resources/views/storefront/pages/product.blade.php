@extends('storefront.layouts.catalog')
@section('title', $product->name)
@section('content')
    <div class="p07-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}">Home</a><span aria-hidden="true">/</span>
        <a href="{{ route('storefront.shop') }}">Shop</a><span aria-hidden="true">/</span>
        <a href="{{ route('storefront.category', $category->slug) }}">{{ $category->name }}</a>
        <span aria-hidden="true">/</span><span>{{ $product->name }}</span>
    </div>
    <div class="p07-detail">
        <div class="p07-product-gallery">
            <img class="p07-main-photo" src="{{ $catalog->productImage($product) }}" alt="{{ $product->name }}" fetchpriority="high">
            @if($product->getMedia('product_gallery')->isNotEmpty())
                <div class="p07-thumbnail-row" aria-label="Product gallery">
                    @foreach($product->getMedia('product_gallery')->take(6) as $galleryImage)
                        <button type="button" data-gallery-src="{{ $galleryImage->getUrl() }}" data-gallery-alt="{{ $product->name }} image {{ $loop->iteration }}" aria-label="Show image {{ $loop->iteration }}">
                            <img src="{{ $galleryImage->getUrl() }}" alt="" loading="lazy">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
        <section class="p07-detail-summary" aria-labelledby="p07-product-name">
            <span class="p07-eyebrow">{{ $category->name }}</span>
            <h1 id="p07-product-name">{{ $product->name }}</h1>
            <div class="p07-detail-price" id="p07-selected-price">{{ $product->has_variants ? 'From ' : '' }}৳{{ number_format($catalog->displayPrice($product)) }}</div>
            @if($product->short_description)<p class="p07-summary-description">{{ $product->short_description }}</p>@endif

            @if($product->has_variants)
                <div id="p07-variant-picker" data-variant-matrix="{{ json_encode($selection, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) }}">
                    @foreach($selection['options'] as $option)
                        <div class="p07-choice-group">
                            <label for="p07-option-{{ $option['id'] }}">{{ $option['name'] }}</label>
                            <select id="p07-option-{{ $option['id'] }}" data-option-id="{{ $option['id'] }}">
                                <option value="">Choose {{ $option['name'] }}</option>
                                @foreach($option['values'] as $value)
                                    <option value="{{ $value['id'] }}">{{ $value['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <p class="p07-variant-status" id="p07-variant-status" role="status" aria-live="polite">Select all options to view exact price and stock.</p>
                    <p class="p07-sku" id="p07-variant-sku"></p>
                </div>
            @else
                <p class="p07-variant-status">{{ $catalog->displayStock($product) > 0 ? 'Available: '.$catalog->displayStock($product) : 'Currently out of stock' }}</p>
                @if($product->weight_size)<p class="p07-sku">{{ $product->weight_size }}</p>@endif
            @endif
            @if($cartVersion !== null)
                @php
                    $simpleDefault = $product->has_variants ? null : $product->variants()->where('is_default', true)->where('is_active', true)->first();
                @endphp
                <form class="p08-product-order" method="POST" action="{{ route('storefront.cart.add') }}">
                    @csrf
                    <input type="hidden" name="cart_version" value="{{ $cartVersion }}">
                    @if($product->has_variants)
                        <label for="p08-variant-id">Selected variation</label>
                        <select name="product_variant_id" id="p08-variant-id" required>
                            <option value="">Choose the exact SKU</option>
                            @foreach($catalog->eligibleVariants($product) as $variant)
                                <option value="{{ $variant->id }}" @disabled($variant->stock < 1)>
                                    {{ $variant->optionValues->map(fn($v) => $v->label)->implode(' / ') }} ({{ $variant->sku }}) — {{ $variant->stock }} available
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="product_variant_id" value="{{ $simpleDefault?->id }}">
                    @endif
                    <label for="p08-product-quantity">Quantity</label>
                    <input type="number" name="quantity" id="p08-product-quantity" min="1" max="1000" value="1" required>
                    <div class="p08-product-actions">
                        <button type="submit" name="intent" value="cart" class="p08-action-cart" @disabled(!$product->has_variants && (!$simpleDefault || $simpleDefault->stock < 1))>Add to Cart</button>
                        @if(config('storefront.p08_checkout_test_enabled', false) && config('storefront.p03_core_test_enabled', false))
                            <button type="submit" name="intent" value="checkout" class="p08-action-buy" @disabled(!$product->has_variants && (!$simpleDefault || $simpleDefault->stock < 1))>Order Now</button>
                        @else
                            <a class="p08-action-buy" href="{{ route('storefront.cart') }}">View Cart</a>
                        @endif
                    </div>
                    <p class="p08-help">Cash on delivery. Prices and availability are checked again before confirmation.</p>
                </form>
            @else
                <div class="p07-checkout-notice" role="note">
                    <strong>Browsing preview</strong>
                    <span>Cart and Order Now will become available only after secure checkout is completed and verified.</span>
                </div>
            @endif
            <a class="p07-back-to-shop" href="{{ route('storefront.shop') }}">Explore more products</a>
        </section>
    </div>

    @if($product->full_description)
        <section class="p07-description" aria-labelledby="p07-description-heading">
            <h2 id="p07-description-heading">Product description</h2>
            <div class="p07-description-body">{{ $product->full_description }}</div>
        </section>
    @endif
@endsection
