<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Stage catalog only; public promotion waits for the P09 release gate. --}}
    <meta name="robots" content="noindex,nofollow">
    <title>{{ config('app.name', 'Storefront') }} — Homepage</title>
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-preview.css') }}">
    @unless($isPreview ?? false)
        <link rel="stylesheet" href="{{ asset('frontend/css/storefront-contact.css') }}">
    @endunless
</head>
<body>
@php
    $defaultTheme = \App\Support\HomeSectionSchema::DEFAULT_THEME;
    $valid = static function ($colors): array {
        if (!is_array($colors)) return [];
        return array_filter($colors, static fn ($value, $key) =>
            in_array($key, \App\Support\HomeSectionSchema::STYLE_KEYS, true)
            && is_string($value)
            && preg_match('/\A#[0-9a-fA-F]{6}\z/D', $value) === 1,
            ARRAY_FILTER_USE_BOTH
        );
    };
    $global = array_merge($defaultTheme, $valid($revision['settings']['theme'] ?? []));
@endphp
@if($isPreview ?? false)
<div class="sf-preview-notice" role="status">
    <span><strong>PRIVATE HOMEPAGE PREVIEW</strong> — Revision #{{ $revision['revision_number'] }} ({{ $revision['status'] }})</span>
    <a href="{{ route('admin.storefront.home.index') }}">Back to admin editor</a>
</div>
@endif
<header class="sf-header"><div class="sf-wrapper sf-header-inner">
    @if($isPreview ?? false)
        <div class="sf-brand"><span class="sf-mark" aria-hidden="true">S</span> {{ config('app.name', 'Storefront') }}</div>
        <nav aria-label="Preview navigation"><span>Home</span><span>Shop</span><span>Categories</span></nav>
    @else
        <a class="sf-brand sf-brand-link" href="{{ route('storefront.home') }}"><span class="sf-mark" aria-hidden="true">S</span> {{ config('app.name', 'Storefront') }}</a>
        <nav aria-label="Shop navigation">
            <a href="{{ route('storefront.home') }}" aria-current="page">Home</a>
            <a href="{{ route('storefront.shop') }}">Shop</a>
            <a href="{{ route('storefront.shop') }}#categories">Categories</a>
            @if(config('storefront.p08_cart_test_enabled', false))
                <a href="{{ route('storefront.cart') }}">Cart</a>
            @endif
        </nav>
    @endif
    @if($isPreview ?? false)<span class="sf-preview-tag">Design preview · Not accepting orders</span>@endif
</div></header>
<main id="main">
    @forelse($revision['sections'] as $section)
        @continue(!$section['is_enabled'])
        @php
            $palette = array_merge($global, $valid($section['styles']));
            // Constant, reviewed CSS variable names; values are strictly validated #RRGGBB tokens.
            $cssTokens = [
                'background' => '--sf-bg', 'heading' => '--sf-heading', 'text' => '--sf-text',
                'accent' => '--sf-accent', 'button_background' => '--sf-btn', 'button_text' => '--sf-btn-text',
                'button_hover_background' => '--sf-btn-hover', 'button_hover_text' => '--sf-btn-hover-text',
                'button_border' => '--sf-btn-border', 'secondary_button_background' => '--sf-secondary',
                'secondary_button_text' => '--sf-secondary-text',
                'secondary_button_hover_background' => '--sf-secondary-hover',
                'secondary_button_hover_text' => '--sf-secondary-hover-text',
                'secondary_button_border' => '--sf-secondary-border',
                'card_background' => '--sf-card', 'card_text' => '--sf-card-text',
                'card_border' => '--sf-border', 'focus_ring' => '--sf-focus',
            ];
            $style = implode(';', array_map(static fn ($token, $css) => $css.':'.$palette[$token],
                array_keys($cssTokens), array_values($cssTokens))).';';
            $visibleClass = !$section['show_mobile'] ? 'sf-hide-mobile' : (!$section['show_desktop'] ? 'sf-hide-desktop' : '');
        @endphp
        <section class="sf-section {{ $visibleClass }} {{ $section['type']==='hero_slider' ? 'sf-section-hero' : '' }}" style="{{ $style }}" aria-label="{{ $section['title'] ?: ucfirst($section['type']) }}">
            <div class="sf-wrapper">
                @if($section['type']==='hero_slider')
                    @foreach($section['items'] as $item)
                        @continue(!$item['is_enabled'] || !$item['media_id'] || !$media->has($item['media_id']))
                        <div class="sf-hero-panel">
                            <div class="sf-hero-copy">
                                <span class="sf-eyebrow">Featured Collection</span>
                                <h1>{{ $item['title'] ?: $section['title'] }}</h1>
                                @if($item['description'])<p>{{ $item['description'] }}</p>@endif
                                @if($item['link_label'])
                                    @if(!($isPreview ?? false) && $item['link_url'])
                                        <a class="sf-cta" href="{{ $item['link_url'] }}" rel="noopener noreferrer">{{ $item['link_label'] }} <span aria-hidden="true">→</span></a>
                                    @else
                                        <span class="sf-cta sf-inert">{{ $item['link_label'] }} <span aria-hidden="true">→</span></span>
                                    @endif
                                @endif
                            </div>
                            <img src="{{ $media[$item['media_id']]->getUrl() }}" alt="{{ $item['title'] ?: 'Featured collection' }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                        </div>
                    @endforeach
                @else
                    @if($section['subtitle'])<p class="sf-kicker">{{ $section['subtitle'] }}</p>@endif
                    <div class="sf-title-line"><h2>{{ $section['title'] }}</h2><span class="sf-title-decoration" aria-hidden="true"></span></div>
                    @if($section['description'])<p class="sf-lead">{{ $section['description'] }}</p>@endif
                    @if($section['type']==='category_grid')
                        <div class="sf-grid sf-grid-categories">
                            @foreach($section['categories'] as $catId)
                                @if($categories->has($catId))
                                    @php $cat = $categories[$catId]; @endphp
                                    <article class="sf-tile">
                                        <div class="sf-square"><img src="{{ ($isPreview ?? false) ? $cat->image : $catalog->categoryImage($cat) }}" alt="" loading="lazy"></div>
                                        @if($isPreview ?? false)
                                            <h3>{{ $cat->name }}</h3>
                                            <span class="sf-meta">Browse category</span>
                                        @else
                                            <h3><a href="{{ route('storefront.category', $cat->slug) }}">{{ $cat->name }}</a></h3>
                                            <a class="sf-meta sf-meta-link" href="{{ route('storefront.category', $cat->slug) }}">Browse category</a>
                                        @endif
                                    </article>
                                @endif
                            @endforeach
                        </div>
                    @elseif($section['type']==='product_list')
                        <div class="sf-grid sf-grid-products">
                            @foreach($section['products'] as $productId)
                                @if($products->has($productId))
                                    @php $product = $products[$productId]; @endphp
                                    <article class="sf-tile sf-product">
                                        <div class="sf-product-image"><img src="{{ ($isPreview ?? false) ? $product->thumbnail : $catalog->productImage($product) }}" alt="{{ $product->name }}" loading="lazy"></div>
                                        <div class="sf-product-copy">
                                            <h3>{{ $product->name }}</h3>
                                            @if($isPreview ?? false)
                                                <div class="sf-price">৳{{ number_format((float) $product->new_price, 0) }}</div>
                                                <div class="sf-product-actions"><span class="sf-secondary sf-inert">Cart</span><span class="sf-cta sf-inert">Buy</span></div>
                                            @else
                                                <div class="sf-price">{{ $product->has_variants ? 'From ' : '' }}৳{{ number_format($catalog->displayPrice($product)) }}</div>
                                                <a class="sf-cta sf-p07-browse" href="{{ route('storefront.product', $product->slug) }}">{{ $product->has_variants ? 'View options' : 'View details' }}</a>
                                            @endif
                                        </div>
                                    </article>
                                @endif
                            @endforeach
                        </div>
                    @elseif($section['type']==='image_text')
                        @foreach($section['items'] as $item)
                            @continue(!$item['is_enabled'] || !$item['media_id'] || !$media->has($item['media_id']))
                            <div class="sf-image-text">
                                <img src="{{ $media[$item['media_id']]->getUrl() }}" alt="{{ $item['title'] ?: 'Collection image' }}" loading="lazy">
                                <div><span class="sf-kicker">Discover More</span><h3>{{ $item['title'] }}</h3>
                                <p>{{ $item['description'] }}</p>
                                @if($item['link_label'])
                                    @if(!($isPreview ?? false) && $item['link_url'])
                                        <a class="sf-cta" href="{{ $item['link_url'] }}" rel="noopener noreferrer">{{ $item['link_label'] }}</a>
                                    @else
                                        <span class="sf-cta sf-inert">{{ $item['link_label'] }}</span>
                                    @endif
                                @endif</div>
                            </div>
                        @endforeach
                    @elseif($section['type']==='reviews')
                        <div class="sf-grid sf-grid-reviews">
                            @foreach($section['items'] as $item)
                                @if($item['is_enabled'] && $item['review_id'] && $reviews->has($item['review_id']))
                                    @php $review=$reviews[$item['review_id']]; @endphp
                                    <figure class="sf-review"><div class="sf-rating">★★★★★</div>
                                        <blockquote>{{ $review->review_text }}</blockquote>
                                        <figcaption>{{ $review->customer_name }}</figcaption>
                                    </figure>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </section>
    @empty
        <section class="sf-empty sf-wrapper"><h1>Build your storefront homepage</h1><p>No homepage sections have been configured in this revision.</p></section>
    @endforelse
</main>
<footer class="sf-footer"><div class="sf-wrapper">
    {{ config('app.name', 'Storefront') }} @if($isPreview ?? false)<span>·</span> Storefront staging preview — existing campaign pages are unaffected @endif
</div></footer>
@if(!($isPreview ?? false))
@include('storefront.partials.contact-widget')
<nav class="sf-mobile-nav" aria-label="Mobile shop navigation">
    <a href="{{ route('storefront.home') }}" aria-current="page">Home</a>
    <a href="{{ route('storefront.shop') }}">Shop</a>
    <a href="{{ route('storefront.shop') }}#categories">Categories</a>
    @if(config('storefront.p08_cart_test_enabled', false))
        <a href="{{ route('storefront.cart') }}">Cart</a>
    @endif
</nav>
@endif
</body></html>
