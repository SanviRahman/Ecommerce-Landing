<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if(($storefrontFavicon = app(\App\Support\StorefrontBranding::class)->favicon()))
        <link rel="icon" href="{{ $storefrontFavicon }}">
    @endif
    @if(($isPreview ?? false) || request()->attributes->get('p26_isolated_commerce_qa', false) === true)
        <meta name="robots" content="noindex,nofollow">
    @endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Storefront') }} — Homepage</title>
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-preview.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-catalog.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-next.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-premium.css') }}">
    @if(!($isPreview ?? false) && config('storefront.p08_cart_test_enabled', false))
        <link rel="stylesheet" href="{{ asset('frontend/css/storefront-cart.css') }}">
    @endif
    @unless($isPreview ?? false)
        <link rel="stylesheet" href="{{ asset('frontend/css/storefront-contact.css') }}">
    @endunless
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p34.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/css/storefront-p35.css') }}">
</head>
<body class="p34-storefront">
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
    $sectionCssTokens = [
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
    $styleForSection = static function (array $section) use ($global, $valid, $sectionCssTokens): string {
        $palette = array_merge($global, $valid($section['styles'] ?? []));
        return implode(';', array_map(static fn ($token, $css) => $css.':'.$palette[$token],
            array_keys($sectionCssTokens), array_values($sectionCssTokens))).';';
    };
@endphp
@if(request()->attributes->get('p25_local_readonly_browse', false))
<div class="p25-readonly-notice" role="status">
    <strong>LOCAL ADMIN STOREFRONT PREVIEW</strong> — Browsing only. Customer login, cart, checkout and orders are disabled.
    <a href="{{ route('admin.storefront.home.index') }}">Back to Homepage Builder</a>
</div>
@endif
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
        {{-- The public reviews follow category shelves, but the private preview keeps editor order. --}}
        @continue(!($isPreview ?? false) && $section['type'] === 'reviews')
        @continue($section['type'] === 'category_products' && !($isPreview ?? false) && ($categoryShelves[$section['section_key']] ?? collect())->isEmpty())
        @php
            $style = $styleForSection($section);
            $visibleClass = !$section['show_mobile'] ? 'sf-hide-mobile' : (!$section['show_desktop'] ? 'sf-hide-desktop' : '');
        @endphp
        <section id="sf-section-{{ $section['section_key'] }}" class="sf-section {{ $visibleClass }} {{ $section['type']==='hero_slider' ? 'sf-section-hero' : '' }}" style="{{ $style }}" aria-label="{{ $section['title'] ?: ucfirst($section['type']) }}">
            <div class="sf-wrapper" data-reveal>
                @if($section['type']==='hero_slider')
                    <div class="sf-hero-carousel" data-hero-carousel aria-roledescription="carousel" aria-label="Homepage featured banners">
                    <div class="sf-hero-track" data-hero-track>
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
                    </div>
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
                        <span id="categories" class="p07-visually-hidden" aria-hidden="true"></span>
                        <div class="sf-grid sf-grid-categories" data-reveal-stagger>
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
                            <div class="sf-grid sf-grid-products sf-dynamic-category {{ ($isPreview ?? false) ? '' : 'p33-products-carousel' }}"
                                @unless($isPreview ?? false) data-product-carousel @endunless data-reveal-stagger>
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
                        <div class="sf-grid sf-grid-products" data-reveal-stagger>
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
                        @include('storefront.partials.home-reviews', ['section' => $section, 'reviews' => $reviews])
                    @endif
                @endif
            </div>
        </section>
    @empty
        <section class="sf-empty sf-wrapper"><h1>Build your storefront homepage</h1><p>No homepage sections have been configured in this revision.</p></section>
    @endforelse
    @unless($isPreview ?? false)
        <aside class="p33-trust-strip sf-wrapper" aria-label="Shopping benefits" data-reveal>
            <div><span class="p33-trust-icon" aria-hidden="true">@include('storefront.partials.icon', ['name'=>'shield'])</span><span><strong>Quality Products</strong><small>Carefully selected items</small></span></div>
            <div><span class="p33-trust-icon" aria-hidden="true">@include('storefront.partials.icon', ['name'=>'check'])</span><span><strong>Trusted Shopping</strong><small>Browse clear product details</small></span></div>
            <div><span class="p33-trust-icon" aria-hidden="true">@include('storefront.partials.icon', ['name'=>'truck'])</span><span><strong>Order Updates</strong><small>Track delivery status</small></span></div>
            <div><span class="p33-trust-icon" aria-hidden="true">@include('storefront.partials.icon', ['name'=>'headphones'])</span><span><strong>Customer Support</strong><small>Contact our team</small></span></div>
        </aside>
        @if(($autoCategories ?? collect())->isNotEmpty() && ! collect($revision['sections'])->contains(fn ($s) => $s['is_enabled'] && $s['type'] === 'category_grid'))
            <section class="p33-auto-section sf-wrapper" id="categories" aria-labelledby="p33-categories-title" data-reveal>
                <div class="p33-section-heading"><div><span class="p33-overline">BROWSE YOUR FAVOURITES</span><h2 id="p33-categories-title">Shop by Category</h2><p>Explore our collections and find exactly what you need.</p></div><a href="{{ route('storefront.shop') }}">View all products →</a></div>
                <div class="sf-grid sf-grid-categories p33-category-grid" data-reveal-stagger>
                    @foreach($autoCategories as $cat)
                        <a class="sf-tile p33-category-tile" href="{{ route('storefront.category', $cat->slug) }}"><span class="sf-square"><img src="{{ $catalog->categoryImage($cat) }}" data-image-fallback="{{ asset('frontend/images/no-image.svg') }}" alt="" loading="lazy"></span><strong>{{ $cat->name }}</strong><small>Explore collection →</small></a>
                    @endforeach
                </div>
            </section>
        @endif
        @if(!empty($autoShelves))
            @foreach($autoShelves as $autoShelf)
                <section class="p33-auto-section sf-wrapper" aria-labelledby="p33-shelf-{{ $autoShelf['category']->id }}" data-reveal>
                    <div class="p33-section-heading"><div><span class="p33-overline">HANDPICKED FOR YOU</span><h2 id="p33-shelf-{{ $autoShelf['category']->id }}">{{ $autoShelf['category']->name }}</h2><p>Shop quality products from this collection.</p></div><div class="p33-heading-tools"><a href="{{ route('storefront.category', $autoShelf['category']->slug) }}">See collection →</a><div class="p33-shelf-buttons"><button type="button" data-carousel-prev aria-label="Previous products">@include('storefront.partials.icon', ['name'=>'chevron-left'])</button><button type="button" data-carousel-next aria-label="Next products">@include('storefront.partials.icon', ['name'=>'chevron-right'])</button></div></div></div>
                    <div class="sf-grid sf-grid-products p33-products-carousel" data-product-carousel data-reveal-stagger>
                        @foreach($autoShelf['products'] as $product)
                            @include('storefront.partials.home-product-card', ['product' => $product, 'isPreview' => false])
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
        {{-- Reviews appear immediately after category-wise shelves in the public storefront.
             Existing draft/preview order and each section's enabled/device/style flags are unchanged. --}}
        @foreach($revision['sections'] as $section)
            @continue(!$section['is_enabled'] || $section['type'] !== 'reviews')
            @php
                $visibleReviews = collect($section['items'])->contains(static fn ($item) =>
                    $item['is_enabled'] && $item['review_id'] && $reviews->has($item['review_id']));
                $reviewVisibility = !$section['show_mobile'] ? 'sf-hide-mobile' : (!$section['show_desktop'] ? 'sf-hide-desktop' : '');
            @endphp
            @if($visibleReviews)
                <section id="sf-section-{{ $section['section_key'] }}" class="sf-section p35-home-reviews {{ $reviewVisibility }}"
                    style="{{ $styleForSection($section) }}" aria-label="{{ $section['title'] ?: 'Customer reviews' }}">
                    <div class="sf-wrapper" data-reveal>
                        @if($section['subtitle'])<p class="sf-kicker">{{ $section['subtitle'] }}</p>@endif
                        <div class="sf-title-line"><h2>{{ $section['title'] ?: 'Customer reviews' }}</h2><span class="sf-title-decoration" aria-hidden="true"></span></div>
                        @if($section['description'])<p class="sf-lead">{{ $section['description'] }}</p>@endif
                        @include('storefront.partials.home-reviews', ['section' => $section, 'reviews' => $reviews])
                    </div>
                </section>
            @endif
        @endforeach
        <section class="p33-stats sf-wrapper" aria-label="Our product collections" data-reveal>
            <div class="p33-stats-label"><span class="p33-overline">WHY SHOP WITH US</span><h2>Better shopping, every day.</h2></div>
            <div><strong data-count-up="{{ (int) ($publicProductCount ?? 0) }}">{{ number_format((int) ($publicProductCount ?? 0)) }}</strong><span>Active products</span></div>
            <div><strong data-count-up="{{ ($autoCategories ?? collect())->count() }}">{{ ($autoCategories ?? collect())->count() }}</strong><span>Collections</span></div>
            <div><strong>24/7</strong><span>Browse online</span></div>
        </section>
        <section class="p33-track-section" id="track-order" aria-labelledby="p33-track-title" data-reveal>
            <div class="sf-wrapper p33-track-inner">
                <div><span class="p33-overline">ORDER UPDATES</span><h2 id="p33-track-title">Track your order</h2><p>Enter the phone number used at checkout. See delivery progress without leaving the storefront.</p></div>
                <div>
                    <form method="POST" action="{{ route('order.track.search') }}" data-storefront-track>
                        @csrf
                        <label for="p33-track-phone">Order phone number</label>
                        <div class="p33-track-controls"><input id="p33-track-phone" type="tel" name="tracking_phone" pattern="01[0-9]{9}" maxlength="11" inputmode="numeric" placeholder="01XXXXXXXXX" autocomplete="tel" required><button type="submit">Track Order →</button></div>
                    </form>
                    <div class="p33-track-results" data-track-results role="status" aria-live="polite"></div>
                </div>
            </div>
        </section>
    @endunless
</main>
@if($isPreview ?? false)
    <p class="sf-preview-release-status">Private draft preview — changes become public only after publishing.</p>
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
<script src="{{ asset('frontend/js/storefront-premium.js') }}" defer></script>
@unless($isPreview ?? false)
    <script src="{{ asset('frontend/js/storefront-catalog.js') }}" defer></script>
@endunless
</body></html>
