@php
    $cartActive = config('storefront.p08_cart_test_enabled', false) === true;
    $account = \Illuminate\Support\Facades\Auth::guard('storefront')->user();
@endphp
<header class="p07-header p19-header" data-sticky-header>
    <div class="p19-benefit-bar"><div class="p07-container">
        <span>✓ Secure shopping</span><span>✓ Cash on delivery</span><span>✓ Support when you need it</span>
        <a href="{{ route('order.track') }}">Track Order</a>
    </div></div>
    <div class="p07-container p19-main-header">
        <a class="p07-brand" href="{{ route('storefront.home') }}" aria-label="{{ config('app.name', 'Storefront') }} homepage">
            <span class="p07-brand-logo" aria-hidden="true">S</span><span>{{ config('app.name', 'Storefront') }}</span>
        </a>
        <form class="p07-search p19-search" method="GET" action="{{ route('storefront.shop') }}" role="search" id="p19-search-form">
            <label class="p07-visually-hidden" for="p07-header-search">Search products and categories</label>
            <input id="p07-header-search" type="search" name="q" placeholder="Search for products..." maxlength="80"
                value="{{ request()->routeIs('storefront.shop') ? request('q', '') : '' }}">
            <button type="submit" aria-label="Search">⌕ <span>Search</span></button>
        </form>
        <div class="p19-header-actions">
            <a href="{{ route('storefront.account.index') }}" class="p19-account-link"><span aria-hidden="true">♙</span>
                <span>{{ $account ? \Illuminate\Support\Str::limit($account->name, 20) : 'Login / Register' }}</span>
            </a>
            @if($cartActive)
                <button type="button" data-cart-open class="p19-top-cart" aria-label="Open shopping cart">
                    <span aria-hidden="true">🛒</span>
                    <span>Cart <b data-cart-count>…</b> · ৳<b data-cart-total>…</b></span>
                </button>
            @endif
        </div>
    </div>
    <nav class="p19-desktop-nav" aria-label="Primary navigation"><div class="p07-container p19-desktop-nav-inner">
        <details class="p19-mega-menu"><summary>☷ All Categories</summary>
            <div class="p19-mega-panel">
                @forelse($navigation as $parent)
                    <details class="p19-parent-category">
                        <summary>{{ $parent->name }}<span aria-hidden="true">›</span></summary>
                        <div class="p19-category-children">
                            <a href="{{ route('storefront.category', $parent->slug) }}">All {{ $parent->name }}</a>
                            @foreach($parent->children as $child)
                                <a href="{{ route('storefront.category', $child->slug) }}">{{ $child->name }}</a>
                            @endforeach
                        </div>
                    </details>
                @empty <p>No active categories</p> @endforelse
            </div>
        </details>
        <a href="{{ route('storefront.home') }}" @if(request()->routeIs('storefront.home')) aria-current="page" @endif>Home</a>
        <a href="{{ route('storefront.shop') }}">Shop</a>
        <a href="{{ route('storefront.shop', ['sort'=>'newest']) }}">New Arrivals</a>
        <a href="{{ route('storefront.shop') }}#categories">Categories</a>
        <a href="{{ route('storefront.help') }}">Help</a>
        <a href="{{ route('storefront.contact') }}">Contact</a>
        <a href="{{ route('order.track') }}">Track Order</a>
    </div></nav>
</header>
<div class="p19-mobile-search-panel" data-mobile-search hidden>
    <form method="GET" action="{{ route('storefront.shop') }}" role="search">
        <label for="p19-mobile-search-input">Search products</label>
        <div><input id="p19-mobile-search-input" name="q" type="search" placeholder="Search products..." maxlength="80" required>
            <button type="submit">Search</button><button type="button" data-search-close aria-label="Close search">✕</button></div>
    </form>
</div>
<div class="p19-drawer-backdrop" data-drawer-backdrop hidden></div>
<aside class="p19-drawer p19-menu-drawer" data-menu-drawer role="dialog" aria-modal="true" aria-labelledby="p19-menu-title" hidden>
    <div class="p19-drawer-head"><h2 id="p19-menu-title">Browse Categories</h2>
        <button type="button" data-drawer-close aria-label="Close menu">✕</button>
    </div>
    <nav aria-label="Categories and shopping" class="p19-drawer-scroll">
        <a class="p19-drawer-link" href="{{ route('storefront.home') }}">⌂ Home</a>
        <a class="p19-drawer-link" href="{{ route('storefront.shop') }}">▦ Shop All Products</a>
        @foreach($navigation as $parent)
            <details class="p19-mobile-category"><summary>{{ $parent->name }}<span>+</span></summary>
                <a href="{{ route('storefront.category', $parent->slug) }}">All {{ $parent->name }}</a>
                @foreach($parent->children as $child)
                    <a href="{{ route('storefront.category', $child->slug) }}">{{ $child->name }}</a>
                @endforeach
            </details>
        @endforeach
        <a class="p19-drawer-link" href="{{ route('storefront.account.index') }}">My Account</a>
        <a class="p19-drawer-link" href="{{ route('order.track') }}">Track Order</a>
    </nav>
</aside>
@if($cartActive)
    <button type="button" class="p19-floating-cart" data-cart-open
        data-cart-summary-url="{{ route('storefront.cart.summary') }}"
        aria-label="Open cart and view total">
        🛒 <b data-cart-count>…</b>
        <span>৳<b data-cart-total>…</b></span>
    </button>
    <aside class="p19-drawer p19-cart-drawer" data-cart-drawer role="dialog" aria-modal="true" aria-labelledby="p19-cart-title" hidden>
        <div class="p19-drawer-head"><h2 id="p19-cart-title">Your Mini Cart</h2>
            <button type="button" data-drawer-close aria-label="Close cart">✕</button>
        </div>
        <div class="p19-drawer-scroll" data-cart-lines><p>Loading your cart…</p></div>
        <div class="p19-cart-drawer-footer"><div>Subtotal: <strong>৳<span data-cart-total>0.00</span></strong></div>
            <a href="{{ route('storefront.cart') }}">View Cart</a>
            @if(config('storefront.p08_checkout_test_enabled', false) && config('storefront.p03_core_test_enabled', false))
                <a class="p19-cart-checkout" href="{{ route('storefront.checkout') }}">Checkout →</a>
            @endif
        </div>
    </aside>
@endif
<nav class="p07-mobile-nav p19-mobile-nav" aria-label="Mobile navigation">
    <a href="{{ route('storefront.home') }}" @if(request()->routeIs('storefront.home')) aria-current="page" @endif><span>⌂</span>Home</a>
    <button type="button" data-menu-open><span>☷</span>Menu</button>
    @if($cartActive)<button type="button" data-cart-open><span>🛒</span>Cart</button>
    @else <a href="{{ route('storefront.shop') }}"><span>▣</span>Shop</a> @endif
    <button type="button" data-search-open><span>⌕</span>Search</button>
    <a href="{{ route('storefront.account.index') }}"><span>♙</span>Account</a>
</nav>
