<header class="p07-header">
    <div class="p07-container p07-header-content">
        <a class="p07-brand" href="{{ route('storefront.home') }}" aria-label="{{ config('app.name', 'Storefront') }} homepage">
            <span class="p07-brand-logo" aria-hidden="true">S</span>
            <span>{{ config('app.name', 'Storefront') }}</span>
        </a>
        <form class="p07-search" method="GET" action="{{ route('storefront.shop') }}" role="search">
            <label class="p07-visually-hidden" for="p07-header-search">Search products</label>
            <input id="p07-header-search" type="search" name="q" placeholder="Search products" maxlength="80" value="{{ request()->routeIs('storefront.shop') ? request('q', '') : '' }}">
            <button type="submit" aria-label="Search products">Search</button>
        </form>
        <nav class="p07-primary-links" aria-label="Main navigation">
            <a href="{{ route('storefront.home') }}" @if(request()->routeIs('storefront.home')) aria-current="page" @endif>Home</a>
            <a href="{{ route('storefront.shop') }}" @if(request()->routeIs('storefront.shop')) aria-current="page" @endif>Shop</a>
            @if(config('storefront.p08_cart_test_enabled', false))
                <a href="{{ route('storefront.cart') }}" @if(request()->routeIs('storefront.cart')) aria-current="page" @endif>Cart</a>
            @endif
            <details class="p07-category-menu">
                <summary>Categories</summary>
                <div class="p07-category-dropdown">
                    @forelse($navigation as $parent)
                        <div class="p07-menu-group">
                            <a class="p07-menu-parent" href="{{ route('storefront.category', $parent->slug) }}">{{ $parent->name }}</a>
                            @foreach($parent->children as $child)
                                <a class="p07-menu-child" href="{{ route('storefront.category', $child->slug) }}">{{ $child->name }}</a>
                            @endforeach
                        </div>
                    @empty
                        <span class="p07-empty-menu">No public categories yet</span>
                    @endforelse
                </div>
            </details>
        </nav>
    </div>
</header>
<nav class="p07-mobile-nav" aria-label="Mobile navigation">
    <a href="{{ route('storefront.home') }}" @if(request()->routeIs('storefront.home')) aria-current="page" @endif>Home</a>
    <a href="{{ route('storefront.shop') }}" @if(request()->routeIs('storefront.shop')) aria-current="page" @endif>Shop</a>
    <a href="{{ route('storefront.shop') }}#categories">Categories</a>
    @if(config('storefront.p08_cart_test_enabled', false))
        <a href="{{ route('storefront.cart') }}" @if(request()->routeIs('storefront.cart')) aria-current="page" @endif>Cart</a>
    @endif
</nav>
