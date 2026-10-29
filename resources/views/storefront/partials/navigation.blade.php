@php
    $branding = app(\App\Support\StorefrontBranding::class);
    $brandName = $branding->name();
    $brandLogo = $branding->logo();
    $commerceActive = request()->attributes->get('p26_isolated_commerce_qa', false) === true
        || request()->attributes->get('p32_local_commerce', false) === true
        || request()->attributes->get('p58_production_commerce') === true;
    $checkoutActive = $commerceActive && config('storefront.p08_checkout_test_enabled', false) === true
        && config('storefront.p03_core_test_enabled', false) === true;
    $wishlistState = app(\App\Support\StorefrontWishlistState::class);
    $wishSignedIn = $wishlistState->authenticated();
    $wishCount = $wishlistState->count();
    $wishlistHref = $wishSignedIn ? route('storefront.wishlist') : route('storefront.account.login');
    $cartActive = $commerceActive && config('storefront.p08_cart_test_enabled', false) === true;
    $account = $commerceActive ? \Illuminate\Support\Facades\Auth::guard('storefront')->user() : null;
@endphp
<header class="p07-header p19-header" data-sticky-header>
    <div class="p19-benefit-bar"><div class="p07-container">
        <span>@include('storefront.partials.icon', ['name'=>'check']) Explore our collection</span>
        <span>@include('storefront.partials.icon', ['name'=>'check']) Discover products</span>
        <span>@include('storefront.partials.icon', ['name'=>'check']) Support when you need it</span>
        <a href="{{ route('order.track') }}">Track Order</a>
    </div></div>
    <div class="p07-container p19-main-header">
        <button type="button" class="p59-header-menu" data-menu-open aria-label="Open category menu" aria-controls="p19-category-menu-drawer">
            @include('storefront.partials.icon', ['name'=>'menu'])
        </button>
        <a class="p07-brand p33-logo-brand" href="{{ route('home') }}" aria-label="{{ $brandName }} homepage">
            @if($brandLogo)
                <img class="p33-site-logo" src="{{ $brandLogo }}" alt="" loading="eager" decoding="async">
            @else
                <span class="p07-brand-logo" aria-hidden="true">{{ mb_strtoupper(mb_substr($brandName, 0, 1)) }}</span>
            @endif
            <span class="p33-brand-name">{{ $brandName }}<small>Shop smart · Live better</small></span>
        </a>
        <form class="p07-search p19-search" method="GET" action="{{ route('storefront.shop') }}" role="search" id="p19-search-form">
            <label class="p07-visually-hidden" for="p07-header-search">Search products and categories</label>
            <input id="p07-header-search" type="search" name="q" placeholder="Search for products..." maxlength="80"
                value="{{ request()->routeIs('storefront.shop') ? request('q', '') : '' }}">
            <button type="submit" aria-label="Search products">@include('storefront.partials.icon', ['name'=>'search'])<span>Search</span></button>
        </form>
        <div class="p19-header-actions p33-header-actions p34-header-actions">
            @if($commerceActive)
                <a href="{{ route('storefront.account.index') }}" class="p19-account-link p33-account" aria-label="My Account{{ $account ? ': '. $account->name : ' — Login or Register' }}">
                    <span class="p33-action-icon">@include('storefront.partials.icon', ['name'=>'account'])</span>
                    <span class="p33-action-copy"><strong>{{ $account ? \Illuminate\Support\Str::limit($account->name, 18) : 'My Account' }}</strong><small>{{ $account ? 'View profile' : 'Login / Register' }}</small></span>
                </a>
            @else
                <a href="{{ route('storefront.shop') }}" class="p19-account-link p33-account" aria-label="Browse products">
                    <span class="p33-action-icon">@include('storefront.partials.icon', ['name'=>'bag'])</span>
                    <span class="p33-action-copy"><strong>Shop Products</strong></span>
                </a>
            @endif
            <a href="{{ $wishlistHref }}" class="p19-account-link p32-nav-wishlist p33-wishlist" aria-label="{{ $wishSignedIn ? 'Wishlist, '.$wishCount.' saved items' : 'Log in to use Wishlist' }}">
                <span class="p33-action-icon">@include('storefront.partials.icon', ['name'=>'heart'])<b class="p33-count-badge" data-wishlist-count>{{ $wishCount }}</b></span>
                <span class="p33-action-copy"><strong>Wishlist</strong><small>{{ $wishCount }} saved items</small></span>
            </a>
            @if($cartActive)
                <button type="button" data-cart-open class="p19-top-cart p33-cart-link" aria-label="Open shopping cart">
                    <span class="p33-action-icon">@include('storefront.partials.icon', ['name'=>'cart'])<b class="p33-count-badge" data-cart-count>…</b></span>
                    <span class="p33-action-copy"><strong>Cart</strong><small>৳<b data-cart-total>…</b></small></span>
                </button>
            @endif
        </div>
    </div>
    <nav class="p19-desktop-nav" aria-label="Primary navigation"><div class="p07-container p19-desktop-nav-inner">
        <details class="p19-mega-menu" data-categories-menu>
            <summary>@include('storefront.partials.icon', ['name'=>'grid']) <span>All Categories</span>@include('storefront.partials.icon', ['name'=>'chevron-down', 'class'=>'p34-mega-chevron'])</summary>
            <div class="p19-mega-panel">
                <div class="p52-menu-intro"><span>EXPLORE COLLECTIONS</span><strong>Find your next favorite</strong></div>
                @forelse($navigation as $parent)
                    @php
                        // Visual-only icons; never infer category or product data from the label.
                        $categoryIcons = ['beauty'=>'sparkles','clothing'=>'shirt','electronics'=>'monitor',
                            'home-kitchen'=>'home','home-and-kitchen'=>'home','natural-oil'=>'leaf','organic-food'=>'leaf'];
                        $categoryIcon = $categoryIcons[$parent->slug] ?? 'grid';
                    @endphp
                    <details class="p19-parent-category">
                        <summary>
                            <span class="p39-category-label">@include('storefront.partials.icon', ['name'=>$categoryIcon]) {{ $parent->name }}</span>
                            @if($parent->children->isNotEmpty())
                                <span class="p40-subcategory-count" aria-label="{{ $parent->children->count() }} subcategories">{{ $parent->children->count() }}</span>
                            @endif
                            @include('storefront.partials.icon', ['name'=>'chevron-right'])
                        </summary>
                        <div class="p19-category-children">
                            <a href="{{ route('storefront.category', $parent->slug) }}"
                               @if(request()->routeIs('storefront.category') && request()->route('slug') === $parent->slug) aria-current="page" @endif>All {{ $parent->name }}</a>
                            @foreach($parent->children as $child)
                                <a href="{{ route('storefront.category', $child->slug) }}"
                                   @if(request()->routeIs('storefront.category') && request()->route('slug') === $child->slug) aria-current="page" @endif>{{ $child->name }}</a>
                            @endforeach
                        </div>
                    </details>
                @empty <p>No active categories</p> @endforelse
            </div>
        </details>
        <a href="{{ route('home') }}" @if(request()->routeIs('home', 'storefront.home')) aria-current="page" @endif>Home</a>
        <a href="{{ route('storefront.shop') }}">Shop</a>
        <a href="{{ route('storefront.shop', ['sort'=>'newest']) }}">New Arrivals</a>
        <a href="{{ route('home') }}#categories">Categories</a>
        <a href="{{ route('storefront.help') }}">Help</a>
        <a href="{{ route('storefront.contact') }}">Contact</a>
        <a href="{{ route('order.track') }}">Track Order</a>
    </div></nav>
</header>
<div class="p19-mobile-search-panel" data-mobile-search hidden>
    <form method="GET" action="{{ route('storefront.shop') }}" role="search">
        <label for="p19-mobile-search-input">Search products</label>
        <div><input id="p19-mobile-search-input" name="q" type="search" placeholder="Search products..." maxlength="80" required>
            <button type="submit" aria-label="Search">@include('storefront.partials.icon', ['name'=>'search'])</button>
            <button type="button" data-search-close aria-label="Close search">@include('storefront.partials.icon', ['name'=>'close'])</button></div>
    </form>
</div>
<div class="p19-drawer-backdrop" data-drawer-backdrop hidden></div>
<aside id="p19-category-menu-drawer" class="p19-drawer p19-menu-drawer" data-menu-drawer role="dialog" aria-modal="true" aria-labelledby="p19-menu-title" hidden>
    <div class="p19-drawer-head"><h2 id="p19-menu-title">Browse Categories</h2>
        <button type="button" data-drawer-close aria-label="Close menu">@include('storefront.partials.icon', ['name'=>'close'])</button>
    </div>
    <nav aria-label="Categories and shopping" class="p19-drawer-scroll">
        <a class="p19-drawer-link" href="{{ route('home') }}">@include('storefront.partials.icon', ['name'=>'home']) Home</a>
        <a class="p19-drawer-link" href="{{ route('storefront.shop') }}">@include('storefront.partials.icon', ['name'=>'grid']) Shop All Products</a>
        <a class="p19-drawer-link" href="{{ $wishlistHref }}">@include('storefront.partials.icon', ['name'=>'heart']) My Wishlist</a>
        @if($cartActive)<a class="p19-drawer-link" href="{{ route('storefront.cart') }}">@include('storefront.partials.icon', ['name'=>'cart']) My Cart</a>@endif
        @if($checkoutActive)<a class="p19-drawer-link" href="{{ route('storefront.checkout') }}">Secure Checkout</a>@endif
        @foreach($navigation as $parent)
            <details class="p19-mobile-category"><summary>{{ $parent->name }}
                <span class="p40-mobile-count">{{ $parent->children->count() ?: '+' }}</span></summary>
                <a href="{{ route('storefront.category', $parent->slug) }}">All {{ $parent->name }}</a>
                @foreach($parent->children as $child)
                    <a href="{{ route('storefront.category', $child->slug) }}">{{ $child->name }}</a>
                @endforeach
            </details>
        @endforeach
        @if($commerceActive)
            <a class="p19-drawer-link" href="{{ route('storefront.account.index') }}">@include('storefront.partials.icon', ['name'=>'account']) My Account</a>
        @else
            <a class="p19-drawer-link" href="{{ route('storefront.contact') }}">Contact Us</a>
        @endif
        <a class="p19-drawer-link" href="{{ route('order.track') }}">Track Order</a>
    </nav>
</aside>
@if($cartActive)
    <button type="button" class="p19-floating-cart" data-cart-open
        data-cart-summary-url="{{ route('storefront.cart.summary') }}" aria-label="Open cart and view total">
        @include('storefront.partials.icon', ['name'=>'cart']) <b data-cart-count>…</b>
        <span>৳<b data-cart-total>…</b></span>
    </button>
    <button type="button" class="p35-mobile-cart-peek" data-cart-open
        aria-label="Open mini cart" aria-describedby="p35-mobile-cart-label">
        <span class="p35-mini-cart-symbol" aria-hidden="true">@include('storefront.partials.icon', ['name'=>'cart'])</span>
        <span class="p35-mini-cart-info" id="p35-mobile-cart-label">
            <strong data-p35-cart-peek-title>Your shopping bag</strong>
            <small><b data-cart-count>…</b> items · ৳<b data-cart-total>…</b></small>
        </span>
        <span class="p35-mini-cart-action" data-p35-cart-peek-action>Open cart @include('storefront.partials.icon', ['name'=>'arrow-right'])</span>
    </button>
    <aside class="p19-drawer p19-cart-drawer" data-cart-drawer role="dialog" aria-modal="true" aria-labelledby="p19-cart-title" hidden>
        <div class="p19-drawer-head"><h2 id="p19-cart-title">Your Mini Cart</h2>
            <button type="button" data-drawer-close aria-label="Close cart">@include('storefront.partials.icon', ['name'=>'close'])</button>
        </div>
        <div class="p19-drawer-scroll" data-cart-lines><p>Loading your cart…</p></div>
        <div class="p19-cart-drawer-footer"><div>Subtotal: <strong>৳<span data-cart-total>0.00</span></strong></div>
            <a href="{{ route('storefront.cart') }}">View Cart</a>
            @if($checkoutActive)<a class="p19-cart-checkout" href="{{ route('storefront.checkout') }}">Checkout →</a>@endif
        </div>
    </aside>
@endif
<nav class="p07-mobile-nav p19-mobile-nav p34-mobile-nav" aria-label="Mobile navigation">
    <a href="{{ route('home') }}" @if(request()->routeIs('home', 'storefront.home')) aria-current="page" @endif>
        <span class="p34-bottom-icon">@include('storefront.partials.icon', ['name'=>'home'])</span><span class="p34-mobile-text">Home</span>
    </a>
    <button type="button" data-menu-open aria-label="Open category menu">
        <span class="p34-bottom-icon">@include('storefront.partials.icon', ['name'=>'grid'])</span><span class="p34-mobile-text">Menu</span>
    </button>
    @if($cartActive)
        <button type="button" data-cart-open class="p34-bottom-cart" aria-label="Open cart">
            <span class="p34-bottom-icon">@include('storefront.partials.icon', ['name'=>'cart'])<b class="p34-nav-count" data-cart-count>…</b></span>
            <span class="p34-mobile-text">Cart</span>
        </button>
    @endif
    <button type="button" data-search-open aria-label="Search products">
        <span class="p34-bottom-icon">@include('storefront.partials.icon', ['name'=>'search'])</span><span class="p34-mobile-text">Search</span>
    </button>
    @if($commerceActive)
        <a href="{{ route('storefront.account.index') }}" @if(request()->routeIs('storefront.account.*')) aria-current="page" @endif aria-label="My Account">
            <span class="p34-bottom-icon">@include('storefront.partials.icon', ['name'=>'account'])</span><span class="p34-mobile-text">Account</span>
        </a>
    @else
        <a href="{{ route('storefront.contact') }}" aria-label="Contact support">
            <span class="p34-bottom-icon">@include('storefront.partials.icon', ['name'=>'headphones'])</span><span class="p34-mobile-text">Contact</span>
        </a>
    @endif
</nav>
