<footer class="p07-footer p19-footer">
    <div class="p07-container">
        <div class="p19-newsletter"><div><span class="p19-caption">STAY IN THE LOOP</span>
            <h2>Quality products. Better shopping.</h2><p>Browse curated collections and find your next favourite.</p>
        </div><a class="p19-newsletter-cta" href="{{ route('storefront.shop') }}">Explore Products →</a></div>
        <div class="p19-footer-grid">
            <div><a class="p19-footer-brand" href="{{ route('storefront.home') }}">{{ config('app.name', 'Storefront') }}</a>
                <p>A convenient store for products selected for your everyday needs.</p>
                <small>Secure forms · Authentic stock information · COD on supported orders</small>
            </div>
            <div><h3>Quick Links</h3>
                <a href="{{ route('storefront.home') }}">Home</a><a href="{{ route('storefront.shop') }}">Shop</a>
                <a href="{{ route('storefront.shop') }}#categories">Categories</a>
                <a href="{{ route('storefront.account.index') }}">My Account</a>
                <a href="{{ route('storefront.contact') }}">Contact</a>
            </div>
            <div><h3>Customer Care</h3>
                <a href="{{ route('order.track') }}">Track Order</a>
                @if(config('storefront.p08_cart_test_enabled', false))<a href="{{ route('storefront.cart') }}">My Cart</a>@endif
                <a href="{{ route('storefront.account.login') }}">Login / Register</a>
                <a href="{{ route('storefront.help') }}">FAQ / Help</a>
                <p>For shipping, returns or support, contact our team.</p>
            </div>
            <div><h3>Contact Us</h3>
                @foreach(app(\App\Support\StorefrontContactLinks::class)->active() as $contact)
                    <a href="{{ $contact['href'] }}" @if($contact['external']) target="_blank" rel="noopener noreferrer" @endif>{{ $contact['label'] }}</a>
                @endforeach
            </div>
        </div>
        <div class="p19-footer-bottom"><span>© {{ now()->year }} {{ config('app.name', 'Storefront') }}</span>
            <span>Storefront release is independently gated from the existing campaign homepage.</span></div>
    </div>
</footer>
