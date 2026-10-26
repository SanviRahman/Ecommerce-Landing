<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Stage catalog only; public promotion waits for the P09 release gate. --}}
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Storefront') }} — Homepage</title>
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-preview.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-catalog.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-next.css') }}">
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
@if($isPreview ?? false)
    @php
        // Preview section links scroll inside this private page. They never route
        // to the gated public catalog, cart or checkout.
        $previewCategoriesSection = collect($revision['sections'])->first(static fn ($s) =>
            $s['is_enabled'] && $s['type'] === 'category_grid');
        $previewProductSection = collect($revision['sections'])->first(static fn ($s) =>
            $s['is_enabled'] && in_array($s['type'], ['category_products', 'product_list'], true));
        $previewCategoriesAnchor = $previewCategoriesSection ? '#sf-section-'.$previewCategoriesSection['section_key'] : '#main';
        $previewProductsAnchor = $previewProductSection ? '#sf-section-'.$previewProductSection['section_key'] : '#main';
    @endphp
<header class="sf-header"><div class="sf-wrapper sf-header-inner">
    <a class="sf-brand sf-brand-link" href="#main"><span class="sf-mark" aria-hidden="true">S</span> {{ config('app.name', 'Storefront') }}</a>
    <nav aria-label="Preview section navigation">
        <a href="#main">Home</a>
        <a href="{{ $previewProductsAnchor }}">Shop section</a>
        <a href="{{ $previewCategoriesAnchor }}">Categories section</a>
    </nav>
    <span class="sf-preview-tag">Design preview · Not accepting orders</span>
</div></header>
@else
    @include('storefront.partials.navigation', ['navigation' => $navigation])
@endif
<main id="main">
    @forelse($revision['sections'] as $section)
        @continue(!$section['is_enabled'])
        @continue($section['type'] === 'category_products' && !($isPreview ?? false) && ($categoryShelves[$section['section_key']] ?? collect())->isEmpty())
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
        <section id="sf-section-{{ $section['section_key'] }}" class="sf-section {{ $visibleClass }} {{ $section['type']==='hero_slider' ? 'sf-section-hero' : '' }}" style="{{ $style }}" aria-label="{{ $section['title'] ?: ucfirst($section['type']) }}">
            <div class="sf-wrapper">
                @if($section['type']==='hero_slider')
                    <div class="sf-hero-carousel" data-hero-carousel aria-roledescription="carousel" aria-label="Homepage featured banners">
                    @foreach($section['items'] as $item)
                        @continue(!$item['is_enabled'] || !$item['media_id'] || !$media->has($item['media_id']))
                        <div class="sf-hero-panel" data-hero-slide role="group" aria-roledescription="slide">
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
                        <div class="sf-carousel-controls" data-hero-controls hidden>
                            <button type="button" data-hero-prev aria-label="Previous banner">‹</button>
                            <div class="sf-carousel-dots" data-hero-dots aria-label="Choose banner"></div>
                            <button type="button" data-hero-next aria-label="Next banner">›</button>
                        </div>
                    </div>
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
                                        <div class="sf-square"><img src="{{ ($isPreview ?? false) ? ($cat->getFirstMediaUrl('category_image') ?: asset('frontend/images/no-image.svg')) : $catalog->categoryImage($cat) }}" data-image-fallback="{{ asset('frontend/images/no-image.svg') }}" alt="" loading="lazy"></div>
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
                    @elseif($section['type']==='category_products')
                        @php $shelf = $categoryShelves[$section['section_key']] ?? collect(); @endphp
                        @if($shelf->isNotEmpty())
                            <div class="sf-grid sf-grid-products sf-dynamic-category" data-reveal-stagger>
                                @foreach($shelf as $product)
                                    @include('storefront.partials.home-product-card', ['product'=>$product, 'isPreview'=>($isPreview ?? false)])
                                @endforeach
                            </div>
                        @else
                            @if($isPreview ?? false)
                                <div class="sf-empty-section-note">No eligible products in this category yet. Add active products or choose a different source category.</div>
                            @endif
                        @endif
                    @elseif($section['type']==='product_list')
                        <div class="sf-grid sf-grid-products">
                            @foreach($section['products'] as $productId)
                                @if($products->has($productId))
                                    @php $product = $products[$productId]; @endphp
                                    @include('storefront.partials.home-product-card', ['product'=>$product, 'isPreview'=>($isPreview ?? false)])
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
                        <div class="sf-reviews-shell" data-review-carousel><div class="sf-grid sf-grid-reviews" data-review-track>
                            @foreach($section['items'] as $item)
                                @if($item['is_enabled'] && $item['review_id'] && $reviews->has($item['review_id']))
                                    @php $review=$reviews[$item['review_id']]; @endphp
                                    <figure class="sf-review"><div class="sf-rating" aria-label="{{ (int)$review->rating }} out of 5 stars">{{ str_repeat("★", max(0, min(5, (int)$review->rating))) }}{{ str_repeat("☆", max(0, 5 - (int)$review->rating)) }}</div>
                                        <blockquote>{{ $review->review_text }}</blockquote>
                                        <figcaption>{{ $review->customer_name }}</figcaption>
                                    </figure>
                                @endif
                            @endforeach
                        </div>
                            <div class="sf-review-controls" data-review-controls hidden>
                                <button type="button" data-review-prev aria-label="Previous review">←</button>
                                <button type="button" data-review-next aria-label="Next review">→</button>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </section>
    @empty
        <section class="sf-empty sf-wrapper"><h1>Build your storefront homepage</h1><p>No homepage sections have been configured in this revision.</p></section>
    @endforelse
</main>
@if($isPreview ?? false)
    <p class="sf-preview-release-status">Private staging preview — public campaign homepage remains unchanged.</p>
    @include('storefront.preview.footer', [
        'previewProductsAnchor' => $previewProductsAnchor,
        'previewCategoriesAnchor' => $previewCategoriesAnchor,
    ])
    <nav class="sf-mobile-nav" aria-label="Preview mobile section navigation">
        <a href="#main">Home</a>
        <a href="{{ $previewCategoriesAnchor }}">Categories</a>
        <a href="{{ $previewProductsAnchor }}">Products</a>
        <a href="{{ route('admin.storefront.home.index') }}">Editor</a>
    </nav>
@else
    @include('storefront.partials.footer')
    @include('storefront.partials.contact-widget')
@endif
<script src="{{ asset('frontend/js/image-fallback.js') }}" defer></script>
<script src="{{ asset('frontend/js/storefront-next.js') }}" defer></script>
@unless($isPreview ?? false)
    <script src="{{ asset('frontend/js/storefront-catalog.js') }}" defer></script>
@endunless
</body></html>
