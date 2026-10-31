"""P47 offline regression checks for account section navigation (no Laravel bootstrap)."""
import pathlib
import re
import sys
import unittest

ROOT = pathlib.Path(sys.argv[1] if len(sys.argv) > 1 else pathlib.Path(__file__).resolve().parents[2]).resolve()
sys.argv = sys.argv[:1]


def read(name: str) -> str:
    return (ROOT / name).read_text(encoding='utf-8')

class AccountNavigationRegression(unittest.TestCase):
    def test_account_controller_keeps_login_and_order_ownership(self):
        s = read('app/Http/Controllers/Frontend/StorefrontAccountController.php')
        self.assertIn("Auth::guard('storefront')->user()", s)
        self.assertIn("$customer->orders()", s)
        self.assertIn('->limit(30)->get()', s)
        self.assertIn("['dashboard', 'orders', 'profile']", s)
        self.assertIn("? $requestedTab\n            : 'dashboard'", s)
        self.assertIn("compact('customer', 'orders', 'orderStats', 'activeTab')", s)

    def test_account_navigation_is_not_a_scroll_only_anchor(self):
        s = read('resources/views/storefront/account/index.blade.php')
        for tab in ['dashboard', 'orders', 'profile']:
            self.assertIn("['tab' => '%s']" % tab, s)
            self.assertIn("$activeTab === '%s'" % tab, s)
        self.assertNotIn('href="#p19-account-orders"', s)
        self.assertIn('#p19-account-overview', s)
        self.assertIn('#p19-account-profile', s)

    def test_orders_show_dashboard_and_not_profile_form(self):
        s = read('resources/views/storefront/account/index.blade.php')
        orders_block, profile_block = s.split("        @else\n            <section id=\"p19-account-profile\"", 1)
        self.assertIn("@if($activeTab !== 'profile')", orders_block)
        self.assertIn('>Dashboard</h1>', orders_block)
        self.assertIn('id="p19-account-orders"', orders_block)
        self.assertNotIn('p19-profile-form', orders_block)
        self.assertIn('p19-profile-form', profile_block)
        self.assertIn('@csrf @method(\'PATCH\')', profile_block)
        self.assertIn('@forelse($orders as $order)', s)

    def test_wishlist_returns_to_dashboard(self):
        s = read('resources/views/storefront/pages/wishlist.blade.php')
        self.assertIn('Back to Dashboard', s)
        self.assertIn("route('storefront.account.index', ['tab' => 'dashboard'])", s)
        self.assertIn('#p19-account-overview', s)
        self.assertIn('@foreach($products as $product)', s)

    def test_tracking_return_is_signed_in_only_and_preserves_search(self):
        s = read('resources/views/storefront/pages/track-order.blade.php')
        self.assertIn("Auth::guard('storefront')->user()?->is_active", s)
        self.assertIn('Back to Dashboard', s)
        self.assertIn("route('storefront.account.index', ['tab' => 'dashboard'])", s)
        self.assertIn('route(\'order.track.search\')', s)
        self.assertIn('@csrf', s)

    def test_styles_scoped_and_loaded_on_all_storefront_pages(self):
        layout = read('resources/views/storefront/layouts/catalog.blade.php')
        css = read('public/frontend/css/storefront-p47.css')
        self.assertIn('frontend/css/storefront-p47.css', layout)
        self.assertIn('.p34-storefront .p47-account-menu', css)
        self.assertIn('.p34-storefront .p47-dashboard-return', css)
        self.assertIn('scroll-margin-top:', css)

    def test_existing_routes_and_wishlist_auth_unchanged(self):
        routes = read('routes/storefront.php')
        self.assertIn("Route::get('/', [StorefrontAccountController::class, 'index'])->name('index');",routes)
        self.assertIn("Route::get('/wishlist', [StorefrontWishlistController::class, 'index'])->name('wishlist');",routes)
        s=read('app/Http/Controllers/Frontend/StorefrontWishlistController.php')
        self.assertIn('if (! $wishlist->authenticated())',s)

if __name__ == '__main__':
    unittest.main(verbosity=2)
