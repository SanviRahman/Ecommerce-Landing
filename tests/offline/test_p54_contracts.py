"""P54 static contracts for the 2026-10-29 storefront bugfix; offline, no DB."""
from pathlib import Path
import re
import sys
import unittest

ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(__file__).resolve().parents[2]
sys.argv = sys.argv[:1]

def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

class P54Contracts(unittest.TestCase):
    def test_purchase_form_ajax_and_one_pixel_after_server_success(self):
        p=read('resources/views/storefront/pages/product.blade.php')
        self.assertRegex(p, r'id="p08-product-order-form"[^>]+data-async-cart')
        js=read('public/frontend/js/storefront-next.js')
        self.assertIn('result.saved === true && result.tracking', js)
        self.assertIn('window.SFATracking.addToCart(result.tracking)', js)
        addblock=js[js.index('const result = await response.json()'):js.index("const opener = $('[data-cart-open]');",js.index('const result = await response.json()'))]
        self.assertLess(addblock.index('if (result.redirect)'), addblock.index('await refreshCart()'))
        self.assertLess(addblock.index('window.SFATracking.addToCart'), addblock.index('await refreshCart()'))
        ctrl=read('app/Http/Controllers/Frontend/StorefrontCartController.php')
        self.assertIn("$request->session()->flash('p53_added_item', $pixelItem)", ctrl)
        self.assertIn("'tracking' => $redirectToCheckout ? null : $pixelItem",ctrl)
    def test_checkout_totals_client_estimate_and_server_validation(self):
        blade=read('resources/views/storefront/pages/checkout.blade.php')
        js=read('public/frontend/js/storefront-next.js')
        self.assertIn('data-checkout-total-preview data-subtotal-bdt=',blade)
        self.assertGreaterEqual(blade.count('data-checkout-total-preview'),2)
        self.assertIn('data-shipping-cost=',blade)
        self.assertIn('Estimated Total',blade)
        self.assertIn('asPaisa',js)
        self.assertIn('subtotalPaisa + shippingPaisa',js)
        self.assertIn("option.addEventListener('change', updateEstimate)",js)
        self.assertIn("route('storefront.checkout.place')",blade)
        self.assertIn('recalculated by the server',blade)
    def test_only_desktop_nav_checkout_removed_not_cart_navigation(self):
        blade=read('resources/views/storefront/partials/navigation.blade.php')
        self.assertNotIn("@if($checkoutActive)<a href=\"{{ route('storefront.checkout') }}\">Checkout</a>@endif",blade)
        self.assertIn('p19-cart-checkout',blade)
        self.assertIn("route('storefront.checkout')",blade)
        self.assertIn('Track Order',blade)
    def test_loader_larger_contain_and_original_duration(self):
        blade=read('resources/views/storefront/partials/page-loader.blade.php')
        css=read('public/frontend/css/storefront-p54.css')
        self.assertIn('p54-loader-with-image',blade)
        self.assertIn('object-fit: contain;',css)
        self.assertIn('height: 164px',css)
        self.assertIn('height: 175px',css)
        self.assertIn('const minimum = reducedMotion ? 250 : 1000;',read('public/frontend/js/storefront-p51-loader.js'))
    def test_whatsapp_external_not_embedded_and_copy_fallback(self):
        blade=read('resources/views/storefront/partials/contact-widget.blade.php')
        self.assertIn('whatsapp://send?phone=',blade)
        self.assertIn('https://wa.me/',blade)
        self.assertIn('target="_blank" rel="noopener noreferrer"',blade)
        self.assertIn('data-p52-copy-number',blade)
        self.assertNotIn('<iframe',blade)
        self.assertIn('data-p52-whatsapp-dialog',blade)
    def test_new_css_is_scoped_and_present_on_public_views(self):
        css=read('public/frontend/css/storefront-p54.css')
        self.assertNotIn('body * {',css)
        self.assertTrue(all('body.p34-storefront' in rule for rule in css.split('}')[0:1]))
        for f in ('resources/views/storefront/layouts/catalog.blade.php','resources/views/storefront/pages/home.blade.php'):
            self.assertIn('storefront-p54.css',read(f))
    def test_campaign_webhook_order_and_schema_unchanged(self):
        self.assertFalse(any((ROOT/'database/migrations').glob('*p54*')))
        self.assertIn('order.success',read('routes/user.php'))
        self.assertIn('webhooks.pathao',read('routes/user.php'))

if __name__=='__main__': unittest.main(verbosity=2)
