@php
    /** @var \App\Models\Product $product */
    $available = $catalog->displayStock($product);
    $price = $catalog->displayPrice($product);
@endphp
<article class="p07-card">
    <a href="{{ route('storefront.product', $product->slug) }}" class="p07-card-link" aria-label="View {{ $product->name }}">
        <img src="{{ $catalog->productImage($product) }}" alt="{{ $product->name }}" loading="lazy" class="p07-card-image">
    </a>
    <div class="p07-card-body">
        <span class="p07-card-category">{{ $product->category?->name }}</span>
        <h3><a href="{{ route('storefront.product', $product->slug) }}">{{ $product->name }}</a></h3>
        <div class="p07-card-prices">
            <strong>{{ $product->has_variants ? 'From ' : '' }}৳{{ number_format($price) }}</strong>
            <span class="p07-stock {{ $available > 0 ? 'p07-instock' : 'p07-outstock' }}">
                {{ $available > 0 ? 'In stock' : 'Out of stock' }}
            </span>
        </div>
        <div class="p19-card-actions">
            @php $defaultSku = $product->has_variants ? null : $product->variants->first(fn($v) => $v->is_default && $v->is_active && !$v->trashed() && $v->stock > 0 && (int)$product->inventory_version === 1); @endphp
            @if(($cartVersion ?? null) !== null && $defaultSku)
                <form action="{{ route('storefront.cart.add') }}" method="POST" data-async-cart class="p19-quickform">
                    @csrf
                    <input type="hidden" name="product_variant_id" value="{{ $defaultSku->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="cart_version" value="{{ $cartVersion }}">
                    <button type="submit" name="intent" value="cart" class="p19-cart-action" aria-label="Add {{ $product->name }} to cart">🛒</button>
                    <button type="submit" name="intent" value="checkout" class="p07-card-action">Order Now</button>
                </form>
            @else
                <a class="p19-cart-action" href="{{ route('storefront.product', $product->slug) }}" aria-label="Choose {{ $product->name }} for cart">🛒</a>
                <a class="p07-card-action" href="{{ route('storefront.product', $product->slug) }}">{{ $product->has_variants ? 'Choose Options' : 'Order Now' }}</a>
            @endif
        </div>
    </div>
</article>
