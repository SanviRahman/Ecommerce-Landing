<footer class="p07-footer">
    <div class="p07-container p07-footer-inner">
        <strong>{{ config('app.name', 'Storefront') }}</strong>
        @if(config('storefront.p08_cart_test_enabled', false) && config('storefront.p08_checkout_test_enabled', false) && config('storefront.p03_core_test_enabled', false))
            <span>Cart and cash-on-delivery checkout are available in this restricted storefront.</span>
        @elseif(config('storefront.p08_cart_test_enabled', false))
            <span>Cart review is available. Checkout is not enabled yet.</span>
        @else
            <span>Browse products. Orders and checkout are not enabled on this storefront yet.</span>
        @endif
    </div>
</footer>
