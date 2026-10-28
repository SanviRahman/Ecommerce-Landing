@php
    $commerceActive = request()->attributes->get('p26_isolated_commerce_qa', false) === true
        || request()->attributes->get('p32_local_commerce', false) === true;
    $branding = app(\App\Support\StorefrontBranding::class);
    $details = app(\App\Support\StorefrontContactDetails::class)->get();
    $brandName = $branding->name();
    $brandLogo = $branding->logo();
    $socialLinks = $branding->socialLinks();
@endphp
<footer class="p07-footer p19-footer p37-footer" id="storefront-footer">
    <div class="p07-container">
        <div class="p19-newsletter p37-newsletter"><div><span class="p19-caption">EXPLORE THE COLLECTION</span>
            <h2>Quality products. Better shopping.</h2><p>Explore the latest arrivals and carefully organized categories.</p>
        </div><a class="p19-newsletter-cta" href="{{ route('storefront.shop') }}">Explore Products @include('storefront.partials.icon', ['name'=>'arrow-right'])</a></div>
        <div class="p19-footer-grid p37-footer-grid">
            <div class="p33-footer-about p37-footer-about">
                <a class="p19-footer-brand p33-footer-brand" href="{{ route('home') }}">
                    @if($brandLogo)<img src="{{ $brandLogo }}" alt="" class="p33-footer-logo" loading="lazy">@else<span class="p33-footer-logo-fallback" aria-hidden="true">{{ mb_strtoupper(mb_substr($brandName, 0, 1)) }}</span>@endif
                    <span>{{ $brandName }}<small>Shop smart · Live better</small></span>
                </a>
                @if($details['description'])<p>{{ $details['description'] }}</p>@else<p>Discover our products and collections, all in one place.</p>@endif
                @if($socialLinks->isNotEmpty())
                    <nav class="p33-social-links p37-social-links" aria-label="Official social media profiles">
                        @foreach($socialLinks as $social)
                            <a href="{{ $social['url'] }}" @unless($social['platform'] === 'phone') target="_blank" rel="noopener noreferrer" @endunless
                               class="p33-social-icon p33-social-{{ $social['platform'] }}"
                               aria-label="{{ $social['label'] }}" title="{{ $social['label'] }}">
                                @include('storefront.partials.social-icon', ['platform' => $social['platform']])
                            </a>
                        @endforeach
                    </nav>
                @endif
            </div>
            <nav class="p37-footer-links" aria-label="Quick Links"><h3>Quick Links</h3>
                <a href="{{ route('home') }}">Home</a><a href="{{ route('storefront.shop') }}">Shop All Products</a>
                <a href="{{ route('home') }}#categories">Shop by Category</a>
                <a href="{{ route('storefront.wishlist') }}">My Wishlist</a>
                <a href="{{ route('storefront.contact') }}">Contact Us</a>
            </nav>
            <nav class="p37-footer-links" aria-label="Customer Care"><h3>Customer Care</h3>
                <a href="{{ route('order.track') }}">Track My Order</a>
                @if($commerceActive && config('storefront.p08_cart_test_enabled', false))<a href="{{ route('storefront.cart') }}">My Cart</a>@endif
                @if($commerceActive && config('storefront.p08_checkout_test_enabled', false))<a href="{{ route('storefront.checkout') }}">Checkout</a>@endif
                @if($commerceActive)<a href="{{ route('storefront.account.login') }}">Login / Register</a>@endif
                <a href="{{ route('storefront.help') }}">FAQ / Support</a>
            </nav>
            <div class="p37-footer-contact"><h3>Contact Us</h3>
                @if($details['address'])<div class="p37-footer-contact-row"><span class="p37-footer-contact-icon">@include('storefront.partials.icon', ['name'=>'pin'])</span><span>{{ $details['address'] }}</span></div>@endif
                @if($details['phone'])<a class="p37-footer-contact-row" href="{{ $details['phone_href'] }}"><span class="p37-footer-contact-icon">@include('storefront.partials.icon', ['name'=>'phone'])</span><span>{{ $details['phone'] }}</span></a>@endif
                @if($details['email'])<a class="p37-footer-contact-row" href="{{ $details['email_href'] }}"><span class="p37-footer-contact-icon">@include('storefront.partials.icon', ['name'=>'mail'])</span><span>{{ $details['email'] }}</span></a>@endif
                @if($details['working_hours'])<div class="p37-footer-contact-row"><span class="p37-footer-contact-icon">@include('storefront.partials.icon', ['name'=>'clock'])</span><span>{{ $details['working_hours'] }}</span></div>@endif
                @unless($details['address'] || $details['phone'] || $details['email'])<p>Contact details will be shown when configured in Site Settings.</p>@endunless
                <a class="p37-footer-contact-cta" href="{{ route('storefront.contact') }}">Send us a message →</a>
            </div>
        </div>
        <div class="p19-footer-bottom p37-footer-bottom"><span>© {{ now()->year }} {{ $brandName }}. All rights reserved.</span></div>
    </div>
</footer>
