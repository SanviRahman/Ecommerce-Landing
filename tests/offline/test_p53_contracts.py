"""P53 static integration guard tests. Run with: python3 tests/offline/test_p53_contracts.py"""
from pathlib import Path
import re
import sys
import unittest

ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(__file__).resolve().parents[2]
sys.argv = sys.argv[:1]

def read(p):
    return (ROOT / p).read_text(encoding='utf-8')

class P53Contracts(unittest.TestCase):
    def test_webhook_routes_remain_shared(self):
        s = read('routes/user.php')
        self.assertIn('webhooks.steadfast', s)
        self.assertIn('webhooks.pathao', s)
        self.assertIn('order.track.search', s)
        for name in ('SteadfastWebhookController.php', 'PathaoWebhookController.php'):
            source = read('app/Http/Controllers/Webhook/' + name)
            self.assertNotIn('whereNotNull(\'campaign_id\')', source)
            self.assertNotIn("where('created_via'", source)
    def test_home_tracking_reuses_public_endpoint(self):
        s = read('resources/views/storefront/partials/footer.blade.php')
        self.assertIn('data-storefront-track', s)
        self.assertIn("route('order.track.search')", s)
        self.assertIn('data-track-results', s)
    def test_pixel_uses_admin_meta_ids_not_arbitrary_scripts(self):
        s = read('resources/views/storefront/partials/meta-pixel.blade.php')
        self.assertIn('TrackingPixel', s)
        self.assertIn("->meta()", s)
        self.assertIn('tracking-pixels.js', s)
        self.assertNotIn('script_code', s)
    def test_both_home_and_catalog_load_shared_tracking(self):
        for path in ['resources/views/storefront/layouts/catalog.blade.php', 'resources/views/storefront/pages/home.blade.php']:
            s = read(path)
            self.assertIn('storefront.partials.meta-pixel', s)
            self.assertIn('frontend.partials.datalayer-events', s)
            self.assertIn('storefront-p53.js', s)
    def test_product_and_cart_meta_events_after_server_success(self):
        self.assertIn('data-p53-product-tracking', read('resources/views/storefront/pages/product.blade.php'))
        self.assertIn('p53_added_item', read('app/Http/Controllers/Frontend/StorefrontCartController.php'))
        self.assertIn('tracking', read('app/Http/Controllers/Frontend/StorefrontCartController.php'))
        self.assertIn('SFATracking.addToCart', read('public/frontend/js/storefront-next.js'))
        self.assertIn('data-p53-checkout-tracking', read('resources/views/storefront/pages/checkout.blade.php'))
        self.assertIn('analytics.beginCheckout(checkout)', read('public/frontend/js/storefront-p53.js'))
    def test_mobile_sticky_reuses_real_form(self):
        s = read('resources/views/storefront/pages/product.blade.php')
        self.assertIn('data-sticky-order', s)
        self.assertIn('form="p08-product-order-form"', s)
        self.assertIn('data-p35-variant-required', s)
        self.assertIn('--p53-mobile-nav-height', read('public/frontend/css/storefront-p53.css'))
        self.assertIn('primary.getBoundingClientRect().bottom <= 0', read('public/frontend/js/storefront-next.js'))
    def test_public_description_justify_and_admin_exclusion(self):
        css = read('public/frontend/css/storefront-p53.css')
        self.assertIn('text-align: justify', css)
        self.assertIn('body.p34-storefront', css)
        self.assertNotIn('body * {text-align:', css)
        self.assertIn('public-description-p53.css', read('resources/views/frontend/layouts/master.blade.php'))
    def test_no_migration_or_webhook_new_endpoint(self):
        self.assertFalse(any((ROOT / 'database/migrations').glob('*p53*')))
        self.assertNotIn('/webhooks/storefront', read('routes/user.php'))

if __name__ == '__main__':
    unittest.main(verbosity=2)
