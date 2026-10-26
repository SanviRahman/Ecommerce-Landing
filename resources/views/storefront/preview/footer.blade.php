{{-- All links in this private footer are real, safe destinations. No mock checkout/account buttons. --}}
<footer class="sf-footer sf-preview-footer" aria-label="Storefront design preview footer">
    <div class="sf-wrapper">
        <div class="sf-preview-footer-grid">
            <div>
                <h2>{{ config('app.name', 'Storefront') }}</h2>
                <p>Private homepage design preview. Public campaign homepage is unchanged.</p>
                <p class="sf-preview-footer-note">Orders, customer accounts and checkout are disabled in this preview.</p>
            </div>
            <nav aria-label="Preview quick links">
                <h2>Quick Links</h2>
                <a href="#main">Home</a>
                <a href="{{ $previewProductsAnchor }}">Products section</a>
                <a href="{{ $previewCategoriesAnchor }}">Categories section</a>
                <a href="{{ route('admin.storefront.home.index') }}">Homepage Editor</a>
            </nav>
            <nav aria-label="Customer care links">
                <h2>Customer Care</h2>
                <a href="{{ route('order.track') }}">Track Order</a>
                <p>Purchasing links are intentionally unavailable in preview mode.</p>
            </nav>
            <div>
                <h2>Contact Us</h2>
                @foreach(app(\App\Support\StorefrontContactLinks::class)->active() as $contact)
                    <a href="{{ $contact['href'] }}" @if($contact['external']) target="_blank" rel="noopener noreferrer" @endif>{{ $contact['label'] }}</a>
                @endforeach
            </div>
        </div>
    </div>
</footer>
