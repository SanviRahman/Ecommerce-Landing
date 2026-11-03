@php
    /** @var \App\Models\Product $product */
    $available = $catalog->displayStock($product);
    $price = $catalog->displayPrice($product);
    $productUrl = route('storefront.product', $product->slug);
    $cardActionText = $product->is_free_delivery ? 'Free Delivery' : 'Buy Now';
    $wishlistState = app(\App\Support\StorefrontWishlistState::class);
    $wishSignedIn = $wishlistState->authenticated();
    $saved = $wishSignedIn && $wishlistState->contains((int) $product->id);
    $quickVariants = (int) $product->inventory_version === 1
        ? ($product->has_variants
            ? $catalog->eligibleVariants($product)->filter(fn ($v) => \App\Services\Inventory\StorefrontDeferredStock::availableForVariant((int) $v->id, (int) $v->stock) > 0)->values()
            : $product->variants->filter(fn ($v) => $v->is_default && $v->is_active
                && !$v->trashed() && \App\Services\Inventory\StorefrontDeferredStock::availableForVariant((int) $v->id, (int) $v->stock) > 0)->values())
        : collect();
    $canAdd = ($cartVersion ?? null) !== null && $quickVariants->count() === 1;
    $canCheckout = $canAdd && config('storefront.p08_checkout_test_enabled', false) === true
        && config('storefront.p03_core_test_enabled', false) === true;
@endphp
<article class="p07-card p34-product-card" data-reveal>
    <a href="{{ $productUrl }}" class="p07-card-link" aria-label="View {{ $product->name }}">
        <img src="{{ $catalog->productImage($product) }}" alt="{{ $product->name }}" loading="lazy" class="p07-card-image">
    </a>
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
    <div class="p07-card-body">
        <span class="p07-card-category">{{ $product->category?->name }}</span>
        <h3><a href="{{ $productUrl }}" title="{{ $product->name }}">{{ $product->name }}</a></h3>
        <div class="p07-card-prices">
            <strong>{{ $product->has_variants ? 'From ' : '' }}৳{{ number_format($price) }}</strong>
            <span class="p07-stock {{ $available > 0 ? 'p07-instock' : 'p07-outstock' }}">
                {{ $available > 0 ? 'In stock' : 'Out of stock' }}
            </span>
        </div>
        <div class="p19-card-actions p34-product-actions">
            @if($canAdd)
                <form action="{{ route('storefront.cart.add') }}" method="POST" data-async-cart class="p19-quickform">
                    @csrf
                    <input type="hidden" name="product_variant_id" value="{{ $quickVariants->first()->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="cart_version" value="{{ $cartVersion }}">
                    <button type="submit" name="intent" value="cart" class="p19-cart-action" aria-label="Add {{ $product->name }} to cart" title="Add to Cart">
                        @include('storefront.partials.icon', ['name'=>'cart-plus'])
                    </button>
                    @if($canCheckout)
                        <button type="submit" name="intent" value="checkout" class="p07-card-action p34-buy-button">{{ $cardActionText }} @include('storefront.partials.icon', ['name'=>'arrow-right'])</button>
                    @else
                        <a class="p07-card-action p34-buy-button" href="{{ $productUrl }}">{{ $cardActionText }} @include('storefront.partials.icon', ['name'=>'arrow-right'])</a>
                    @endif
                </form>
            @else
                <a class="p19-cart-action" href="{{ $product->has_variants ? $productUrl.'#p07-variant-picker' : $productUrl }}" aria-label="{{ $product->has_variants ? 'Choose options for' : 'View' }} {{ $product->name }}" title="{{ $product->has_variants ? 'Choose options on product page' : 'View product details' }}">
                    @include('storefront.partials.icon', ['name'=>'cart-plus'])
                </a>
                <a class="p07-card-action p34-buy-button" href="{{ $product->has_variants ? $productUrl.'#p07-variant-picker' : $productUrl }}">
                    {{ $cardActionText }}
                    @include('storefront.partials.icon', ['name'=>'arrow-right'])
                </a>
            @endif
        </div>
    </div>
</article>
