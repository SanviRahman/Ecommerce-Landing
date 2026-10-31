@php
    $productPreview = (bool) ($isPreview ?? false);
    $price = $productPreview ? (int) $product->new_price : $catalog->displayPrice($product);
    $photo = $product->getFirstMediaUrl('product_thumbnail') ?: asset('frontend/images/no-image.svg');
    $productUrl = route('storefront.product', $product->slug);
    $wishlistState = app(\App\Support\StorefrontWishlistState::class);
    $wishSignedIn = $wishlistState->authenticated();
    $saved = $wishSignedIn && $wishlistState->contains((int) $product->id);
    $quickVariants = !$productPreview && (int) $product->inventory_version === 1
        ? ($product->has_variants
            ? $catalog->eligibleVariants($product)->filter(fn ($v) => \App\Services\Inventory\StorefrontDeferredStock::availableForVariant((int) $v->id, (int) $v->stock) > 0)->values()
            : $product->variants->filter(fn ($v) => $v->is_default && $v->is_active
                && !$v->trashed() && \App\Services\Inventory\StorefrontDeferredStock::availableForVariant((int) $v->id, (int) $v->stock) > 0)->values())
        : collect();
    // Homepage cards never display SKU/option selectors. Direct cart writes are
    // allowed only for an unambiguous, available SKU. Multiple eligible SKUs
    // are chosen on the product detail page (no guessed customer selection).
    $canQuickAdd = !$productPreview && ($cartVersion ?? null) !== null && $quickVariants->count() === 1;
    $selectionUrl = $product->has_variants ? $productUrl.'#p07-variant-picker' : $productUrl;
    $canCheckout = $canQuickAdd && config('storefront.p08_checkout_test_enabled', false) === true
        && config('storefront.p03_core_test_enabled', false) === true;
@endphp
<article class="sf-tile sf-product p34-product-card" data-reveal>
    <a class="sf-product-image" href="{{ $productPreview ? '#' : $productUrl }}"
       @if($productPreview) aria-disabled="true" tabindex="-1" @endif>
        <img src="{{ $photo }}" data-image-fallback="{{ asset('frontend/images/no-image.svg') }}" alt="{{ $product->name }}" loading="lazy">
    </a>
    @unless($productPreview)
        @if($wishSignedIn)
        <form method="POST" action="{{ route('storefront.wishlist.toggle') }}" class="p32-card-wishlist">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <button type="submit" aria-label="{{ $saved ? 'Remove ' : 'Save ' }}{{ $product->name }} {{ $saved ? 'from' : 'to' }} wishlist">
                @include('storefront.partials.icon', ['name'=>$saved ? 'heart-fill' : 'heart'])
            </button>
        </form>
    @else
        <a class="p32-card-wishlist p39-guest-heart" href="{{ route('storefront.account.login') }}"
           aria-label="Log in or register to save {{ $product->name }} to your wishlist" title="Log in to save to wishlist">
            @include('storefront.partials.icon', ['name'=>'heart'])
        </a>
    @endif
    @endunless
    <div class="sf-product-copy">
        <p class="sf-product-category">{{ $product->category?->name }}</p>
        <h3><a href="{{ $productPreview ? '#' : $productUrl }}" @if($productPreview) tabindex="-1" aria-disabled="true" @endif>{{ $product->name }}</a></h3>
        <div class="sf-price">{{ $product->has_variants ? 'From ' : '' }}৳{{ number_format($price) }}</div>
        <div class="sf-product-actions p34-product-actions">
            @if($productPreview)
                <span class="sf-secondary sf-inert" title="Preview only">@include('storefront.partials.icon', ['name'=>'cart-plus'])</span>
                <span class="sf-cta sf-inert">Preview only</span>
            @elseif($canQuickAdd)
                <form action="{{ route('storefront.cart.add') }}" method="POST" data-async-cart class="sf-product-quickform">
                    @csrf
                    <input type="hidden" name="product_variant_id" value="{{ $quickVariants->first()->id }}">
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
                <a class="sf-secondary" href="{{ $selectionUrl }}" aria-label="{{ $product->has_variants ? 'Select a variation for' : 'View' }} {{ $product->name }}" title="{{ $product->has_variants ? 'Select size or color on product page' : 'View product details' }}">@include('storefront.partials.icon', ['name'=>'cart-plus'])</a>
                <a class="sf-cta p34-buy-button" href="{{ $selectionUrl }}">
                    {{ $product->has_variants ? 'Choose Options' : 'View Product' }}
                    @include('storefront.partials.icon', ['name'=>'arrow-right'])
                </a>
            @endif
        </div>
    </div>
</article>
