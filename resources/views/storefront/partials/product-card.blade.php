@php
    /** @var \App\Models\Product $product */
    $available = $catalog->displayStock($product);
    $price = $catalog->displayPrice($product);
    $productUrl = route('storefront.product', $product->slug);
    $saved = in_array((int) $product->id, (array) session('storefront_wishlist_ids', []), true);
    $defaultSku = $product->has_variants ? null : $product->variants->first(
        fn ($v) => $v->is_default && $v->is_active && !$v->trashed() && $v->stock > 0 && (int) $product->inventory_version === 1
    );
    $canAdd = ($cartVersion ?? null) !== null && $defaultSku !== null;
    $canCheckout = $canAdd && config('storefront.p08_checkout_test_enabled', false) === true
        && config('storefront.p03_core_test_enabled', false) === true;
@endphp
<article class="p07-card p34-product-card" data-reveal>
    <a href="{{ $productUrl }}" class="p07-card-link" aria-label="View {{ $product->name }}">
        <img src="{{ $catalog->productImage($product) }}" alt="{{ $product->name }}" loading="lazy" class="p07-card-image">
    </a>
    <form method="POST" action="{{ route('storefront.wishlist.toggle') }}" class="p32-card-wishlist">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <button type="submit" aria-label="{{ $saved ? 'Remove ' : 'Save ' }}{{ $product->name }} {{ $saved ? 'from' : 'to' }} wishlist">
            @include('storefront.partials.icon', ['name'=>$saved ? 'heart-fill' : 'heart'])
        </button>
    </form>
    <div class="p07-card-body">
        <span class="p07-card-category">{{ $product->category?->name }}</span>
        <h3><a href="{{ $productUrl }}">{{ $product->name }}</a></h3>
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
                    <input type="hidden" name="product_variant_id" value="{{ $defaultSku->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="cart_version" value="{{ $cartVersion }}">
                    <button type="submit" name="intent" value="cart" class="p19-cart-action" aria-label="Add {{ $product->name }} to cart" title="Add to Cart">
                        @include('storefront.partials.icon', ['name'=>'cart-plus'])
                    </button>
                    @if($canCheckout)
                        <button type="submit" name="intent" value="checkout" class="p07-card-action p34-buy-button">Buy Now @include('storefront.partials.icon', ['name'=>'arrow-right'])</button>
                    @else
                        <a class="p07-card-action p34-buy-button" href="{{ $productUrl }}">View Product @include('storefront.partials.icon', ['name'=>'arrow-right'])</a>
                    @endif
                </form>
            @else
                <a class="p19-cart-action" href="{{ $productUrl }}" aria-label="{{ $product->has_variants ? 'Choose options for' : 'View' }} {{ $product->name }}" title="{{ $product->has_variants ? 'Choose options first' : 'View product details' }}">
                    @include('storefront.partials.icon', ['name'=>'cart-plus'])
                </a>
                <a class="p07-card-action p34-buy-button" href="{{ $productUrl }}">
                    {{ $product->has_variants ? 'Choose Options' : (($cartVersion ?? null) !== null && $available > 0 && config('storefront.p08_checkout_test_enabled', false) && config('storefront.p03_core_test_enabled', false) ? 'Buy Now' : 'View Product') }}
                    @include('storefront.partials.icon', ['name'=>'arrow-right'])
                </a>
            @endif
        </div>
    </div>
</article>
