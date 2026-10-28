"""P52 scoped source regression contracts (read-only; no external services)."""
import pathlib
import unittest
ROOT = pathlib.Path(__file__).resolve().parents[2]

def source(path): return (ROOT / path).read_text(encoding='utf-8')

class P52Checks(unittest.TestCase):
    def test_loader_time_is_requested_5_to_6_seconds(self):
        s = source('public/frontend/js/storefront-p51-loader.js')
        self.assertIn('5500', s)
        self.assertIn('pageshow', s)
        self.assertIn('p51-ready', s)

    def test_checkout_empty_cart_redirect_not_abort(self):
        s = source('app/Http/Controllers/Frontend/StorefrontCartController.php')
        body = s[s.index('public function checkout('):s.index('public function place(')]
        self.assertNotIn("abort(409, 'Please review your cart before checkout.')", body)
        self.assertIn("route('storefront.cart')", body)

    def test_profile_validated_fields(self):
        s = source('app/Http/Controllers/Frontend/StorefrontAccountController.php')
        for key in ('gender', 'date_of_birth', 'division', 'district', 'upazila', 'profile_photo', 'remove_photo'):
            self.assertIn("'"+key+"'", s)
        self.assertIn("Auth::guard('storefront')->user()",s)
        self.assertIn('image',s)

    def test_profile_additive_migration(self):
        s = source('database/migrations/2026_10_28_120001_add_storefront_account_profile_fields.php')
        self.assertIn('P02MigrationSafety::assertApprovedP02Up()',s)
        self.assertIn("Schema::table('storefront_accounts'",s)
        for field in ('gender', 'date_of_birth', 'division', 'district', 'upazila'):
            self.assertIn("'" + field + "'", s)
        self.assertNotIn('profile_photo_path', s)

    def test_premium_account_ui(self):
        s = source('resources/views/storefront/account/index.blade.php')
        for part in ('Personal Information','Address Information','Profile Photo','Date of Birth','Save Profile','enctype="multipart/form-data"'):
            self.assertIn(part,s)
        self.assertIn('p52-profile-save',s)

    def test_wa_whatsapp_offline_fallback(self):
        s = source('resources/views/storefront/partials/contact-widget.blade.php')
        self.assertIn('data-p52-whatsapp',s)
        self.assertIn('p52-whatsapp-dialog',s)
        self.assertIn('Copy number',s)
        s = source('public/frontend/js/storefront-p52.js')
        self.assertIn('showModal',s)
        self.assertIn('navigator.clipboard',s)

    def test_navigation_enhancement_is_scoped(self):
        s = source('resources/views/storefront/partials/navigation.blade.php')
        self.assertIn('p52-menu-intro',s)
        s = source('public/frontend/css/storefront-p52.css')
        self.assertIn('p19-mega-panel',s)
        self.assertIn('p52-profile-save',s)
        self.assertIn('p08-summary',s)

if __name__=='__main__': unittest.main()
