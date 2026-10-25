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
        <a class="p07-card-action" href="{{ route('storefront.product', $product->slug) }}">
            {{ $product->has_variants ? 'View options' : 'View details' }}
        </a>
    </div>
</article>
