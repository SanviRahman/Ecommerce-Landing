@php
    $productPreview = (bool) ($isPreview ?? false);
    $price = $productPreview ? (int) $product->new_price : $catalog->displayPrice($product);
    $photo = $product->getFirstMediaUrl('product_thumbnail') ?: asset('frontend/images/no-image.svg');
    $productUrl = route('storefront.product', $product->slug);
    $saved = in_array((int) $product->id, (array) session('storefront_wishlist_ids', []), true);
    $defaultSku = $productPreview || $product->has_variants ? null : $product->variants->first(
        fn ($v) => $v->is_default && $v->is_active && !$v->trashed() && $v->stock > 0 && (int) $product->inventory_version === 1
    );
    $canAdd = !$productPreview && ($cartVersion ?? null) !== null && $defaultSku !== null;
    $canCheckout = $canAdd && config('storefront.p08_checkout_test_enabled', false) === true
        && config('storefront.p03_core_test_enabled', false) === true;
@endphp
<article class="sf-tile sf-product p34-product-card" data-reveal>
    <a class="sf-product-image" href="{{ $productPreview ? '#' : $productUrl }}"
       @if($productPreview) aria-disabled="true" tabindex="-1" @endif>
        <img src="{{ $photo }}" data-image-fallback="{{ asset('frontend/images/no-image.svg') }}" alt="{{ $product->name }}" loading="lazy">
    </a>
    @unless($productPreview)
        <form method="POST" action="{{ route('storefront.wishlist.toggle') }}" class="p32-card-wishlist">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <button type="submit" aria-label="{{ $saved ? 'Remove ' : 'Save ' }}{{ $product->name }} {{ $saved ? 'from' : 'to' }} wishlist">
                @include('storefront.partials.icon', ['name'=>$saved ? 'heart-fill' : 'heart'])
            </button>
        </form>
    @endunless
    <div class="sf-product-copy">
        <p class="sf-product-category">{{ $product->category?->name }}</p>
        <h3><a href="{{ $productPreview ? '#' : $productUrl }}" @if($productPreview) tabindex="-1" aria-disabled="true" @endif>{{ $product->name }}</a></h3>
        <div class="sf-price">{{ $product->has_variants ? 'From ' : '' }}৳{{ number_format($price) }}</div>
        <div class="sf-product-actions p34-product-actions">
            @if($productPreview)
                <span class="sf-secondary sf-inert" title="Preview only">@include('storefront.partials.icon', ['name'=>'cart-plus'])</span>
                <span class="sf-cta sf-inert">Preview only</span>
            @elseif($canAdd)
                <form action="{{ route('storefront.cart.add') }}" method="POST" data-async-cart class="sf-product-quickform">
                    @csrf
                    <input type="hidden" name="product_variant_id" value="{{ $defaultSku->id }}">
                    <input type="hidden" name="cart_version" value="{{ $cartVersion }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" name="intent" value="cart" class="sf-secondary" aria-label="Add {{ $product->name }} to cart" title="Add to Cart">@include('storefront.partials.icon', ['name'=>'cart-plus'])</button>
                    @if($canCheckout)
                        <button type="submit" name="intent" value="checkout" class="sf-cta p34-buy-button">Buy Now @include('storefront.partials.icon', ['name'=>'arrow-right'])</button>
                    @else
                        <a class="sf-cta p34-buy-button" href="{{ $productUrl }}">View Product @include('storefront.partials.icon', ['name'=>'arrow-right'])</a>
                    @endif
                </form>
            @else
                <a class="sf-secondary" href="{{ $productUrl }}" aria-label="Choose options or view {{ $product->name }}" title="{{ $product->has_variants ? 'Choose options first' : 'View product details' }}">@include('storefront.partials.icon', ['name'=>'cart-plus'])</a>
                <a class="sf-cta p34-buy-button" href="{{ $productUrl }}">
                    {{ $product->has_variants ? 'Choose Options' : (($cartVersion ?? null) !== null && config('storefront.p08_checkout_test_enabled', false) && config('storefront.p03_core_test_enabled', false) && $catalog->displayStock($product) > 0 ? 'Buy Now' : 'View Product') }}
                    @include('storefront.partials.icon', ['name'=>'arrow-right'])
                </a>
            @endif
        </div>
    </div>
</article>
