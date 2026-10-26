@php
    $commerceActive = request()->attributes->get('p26_isolated_commerce_qa', false) === true
        || request()->attributes->get('p32_local_commerce', false) === true;
    $branding = app(\App\Support\StorefrontBranding::class);
    $brandName = $branding->name();
    $brandLogo = $branding->logo();
    $socialLinks = $branding->socialLinks();
@endphp
<footer class="p07-footer p19-footer">
    <div class="p07-container">
        <div class="p19-newsletter"><div><span class="p19-caption">STAY IN THE LOOP</span>
            <h2>Quality products. Better shopping.</h2><p>Browse curated collections and find your next favourite.</p>
        </div><a class="p19-newsletter-cta" href="{{ route('storefront.shop') }}">Explore Products @include('storefront.partials.icon', ['name'=>'arrow-right'])</a></div>
        <div class="p19-footer-grid">
            <div class="p33-footer-about">
                <a class="p19-footer-brand p33-footer-brand" href="{{ route('home') }}">
                    @if($brandLogo)<img src="{{ $brandLogo }}" alt="" class="p33-footer-logo" loading="lazy">@else<span class="p33-footer-logo-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr($brandName, 0, 1)) }}</span>@endif
                    <span>{{ $brandName }}<small>Shop smart · Live better</small></span>
                </a>
                <p>Your trusted online shopping destination for quality products at fair prices.</p>
                <div class="p33-trust-chips"><span>✓ Secure</span><span>✓ Reliable</span><span>✓ Support</span></div>
                @if($socialLinks->isNotEmpty())
                    <nav class="p33-social-links" aria-label="Social media profiles">
                        @foreach($socialLinks as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="p33-social-icon p33-social-{{ $social['platform'] }}" aria-label="{{ $social['label'] }} (opens a new tab)">
                                @include('storefront.partials.social-icon', ['platform' => $social['platform']])
                            </a>
                        @endforeach
                    </nav>
                @endif
            </div>
            <div><h3>Quick Links</h3>
                <a href="{{ route('home') }}">Home</a><a href="{{ route('storefront.shop') }}">Shop</a>
                <a href="{{ route('home') }}#categories">Categories</a>
                <a href="{{ route('storefront.wishlist') }}">My Wishlist</a>
                @if($commerceActive)
                    <a href="{{ route('storefront.account.index') }}">My Account</a>
                @endif
                <a href="{{ route('storefront.contact') }}">Contact</a>
            </div>
            <div><h3>Customer Care</h3>
                <a href="{{ route('order.track') }}">Track Order</a>
                @if($commerceActive && config('storefront.p08_cart_test_enabled', false))
                    <a href="{{ route('storefront.cart') }}">My Cart</a>
                @endif
                @if($commerceActive && config('storefront.p08_checkout_test_enabled', false))
                    <a href="{{ route('storefront.checkout') }}">Checkout</a>
                @endif
                @if($commerceActive)
                    <a href="{{ route('storefront.account.login') }}">Login / Register</a>
                @endif
                <a href="{{ route('storefront.help') }}">FAQ / Help</a>
                <p>For shipping, returns or support, contact our team.</p>
            </div>
            <div><h3>Contact Us</h3>
                @foreach(app(\App\Support\StorefrontContactLinks::class)->active() as $contact)
                    <a href="{{ $contact['href'] }}" @if($contact['external']) target="_blank" rel="noopener noreferrer" @endif>{{ $contact['label'] }}</a>
                @endforeach
            </div>
        </div>
        <div class="p19-footer-bottom"><span>© {{ now()->year }} {{ $brandName }}</span>
            <span>Storefront and campaign landing pages have separate public URLs.</span></div>
    </div>
</footer>
