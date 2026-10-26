@php
    $productPreview = (bool) ($isPreview ?? false);
    $price = $productPreview ? (int) $product->new_price : $catalog->displayPrice($product);
    $photo = $product->getFirstMediaUrl('product_thumbnail') ?: asset('frontend/images/no-image.svg');
    $productUrl = route('storefront.product', $product->slug);
@endphp
<article class="sf-tile sf-product" data-reveal>
    <a class="sf-product-image" href="{{ $productPreview ? '#' : $productUrl }}"
       @if($productPreview) aria-disabled="true" tabindex="-1" @endif>
        <img src="{{ $photo }}" alt="{{ $product->name }}" loading="lazy">
    </a>
    <div class="sf-product-copy">
        <p class="sf-product-category">{{ $product->category?->name }}</p>
        <h3>{{ $product->name }}</h3>
        <div class="sf-price">{{ $product->has_variants ? 'From ' : '' }}৳{{ number_format($price) }}</div>
        <div class="sf-product-actions">
            @if($productPreview)
                <span class="sf-secondary sf-inert" title="Preview only">🛒</span>
                <span class="sf-cta sf-inert">Order Now</span>
            @else
                @php
                    $defaultSku = $product->has_variants ? null : $product->variants->first(
                        fn($v) => $v->is_default && $v->is_active && !$v->trashed() && $v->stock > 0 && (int)$product->inventory_version === 1
                    );
                @endphp
                @if(($cartVersion ?? null) !== null && $defaultSku)
                    <form action="{{ route('storefront.cart.add') }}" method="POST" data-async-cart class="sf-product-quickform">
                        @csrf
                        <input type="hidden" name="product_variant_id" value="{{ $defaultSku->id }}">
                        <input type="hidden" name="cart_version" value="{{ $cartVersion }}">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit" name="intent" value="cart" class="sf-secondary" aria-label="Add {{ $product->name }} to cart">🛒</button>
                        <button type="submit" name="intent" value="checkout" class="sf-cta">Order Now</button>
                    </form>
                @else
                    <a class="sf-secondary" href="{{ $productUrl }}" aria-label="Select product options for {{ $product->name }}">🛒</a>
                    <a class="sf-cta" href="{{ $productUrl }}">{{ $product->has_variants ? 'Choose Options' : 'Order Now' }}</a>
                @endif
            @endif
        </div>
    </div>
</article>
