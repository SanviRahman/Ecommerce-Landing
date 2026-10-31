"""P61 seven requested production changes: offline structural/security contracts.
No database or network required. Live transaction tests must run on staging.
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]

def source(name: str) -> str:
    return (ROOT / name).read_text(encoding='utf8')

class P61ProductionBugfixContracts(unittest.TestCase):
    def test_deferred_stock_new_checkout_reserves_and_old_test_checkout_allocates(self):
        checkout = source('app/Services/Checkout/StorefrontCheckout.php')
        self.assertIn('StorefrontDeferredStock::checkoutEnabled()', checkout)
        self.assertIn('assertCapacityAndLock($requiredStock)', checkout)
        self.assertIn("'storefront_stock_state' => StorefrontDeferredStock::RESERVED", checkout)
        self.assertIsNotNone(re.search(r'if\s*\(\$deferStock\).*?\}\s*else\s*\{\s*\$this->inventory->allocateStorefrontOrder\(', checkout, re.S))
        self.assertIn('->when(! StorefrontDeferredStock::checkoutEnabled()', checkout)

    def test_finalize_locked_once_with_ledger_and_product_sync(self):
        code = source('app/Services/Inventory/StorefrontDeferredStock.php')
        for check in ('assertStorefrontCheckout()', 'assertAdminManaged()',
                      'assertTransaction()', 'lockForUpdate()', 'self::RESERVED',
                      "'allocate:v1:item:'", "'kind' => 'allocation'",
                      "'inventory_allocated_quantity' => $quantity",
                      "'storefront_stock_state' => self::ALLOCATED", "->decrement('stock', $quantity)"):
            self.assertIn(check, code)
        self.assertIn('whereNull(\'o.deleted_at\')', code)
        self.assertIn("where('o.storefront_stock_state', self::RESERVED)", code)
        self.assertIn('private function assertTransaction()', code)

    def test_deferred_release_and_admin_completion_are_atomically_guarded(self):
        status = source('app/Services/Inventory/ManagedOrderLifecycle.php')
        self.assertIn('StorefrontDeferredStock::RESERVED', status)
        self.assertIn('StorefrontDeferredStock::RELEASED', status)
        self.assertIn('StorefrontDeferredStock::class)->finalize($orderId)', status)
        self.assertIn('DB::transaction(function ()', status)
        self.assertIn("'confirmed', 'complete_invoice'", status)
        self.assertIn('}, 3);', status)
        order = source('app/Http/Controllers/Admin/OrderController.php')
        self.assertIn('markOrdersAsInvoicePrinted', order)
        self.assertIn("Order::STATUS_COMPLETE_INVOICE,", order)
        self.assertIn('app(ManagedOrderLifecycle::class)->transitionStatus(', order)
        self.assertIn('$ids->map(static fn ($id): int => (int) $id)->all()', order)
        self.assertIn('limit-based trash route must respect', order)

    def test_migration_is_non_destructive_and_requires_new_schema_for_checkout(self):
        migration = source('database/migrations/2026_10_31_060001_add_storefront_stock_state_to_orders.php')
        self.assertIn('Schema::hasColumn', migration)
        self.assertIn("->default(0)", migration)
        self.assertIn('Cannot remove the storefront stock state of real orders.', migration)
        access = source('app/Support/P58ProductionStorefrontAccess.php')
        self.assertIn("Schema::hasColumn('orders', 'storefront_stock_state')", access)
        self.assertIn('return false; // New production checkout', access)

    def test_homepage_builder_production_gate_and_no_draft_preview(self):
        gate = source('app/Support/ProductionHomepageAccess.php')
        for invariant in ("config('app.env') === 'production'", 'config(\'app.debug\') === false',
                          "PHP_URL_SCHEME) === 'https'", 'strtolower($request->getHost())',
                          "config('database.default') === 'mysql'", 'SELECT DATABASE() AS schema_name'):
            self.assertIn(invariant, gate)
        self.assertIn('ProductionHomepageAccess::configuredForBuilder()', source('app/Providers/AppServiceProvider.php'))
        self.assertIn('ProductionHomepageAccess::assertActualBuilderDatabase()', source('app/Services/Storefront/HomepageBuilder.php'))
        controller = source('app/Http/Controllers/Admin/HomepageBuilderController.php')
        self.assertIn("if ($id === 0)", controller)
        self.assertIn("'Create a draft before opening the homepage preview.'", controller)
        self.assertIn('whereNull(\'campaign_id\')', controller)

    def test_campaign_uses_only_campaign_owned_reviews(self):
        controller = source('app/Http/Controllers/Admin/CampaignPageController.php')
        self.assertIn("->where('campaign_id', $campaign->id)", controller)
        view = source('resources/views/frontend/pages/home.blade.php')
        self.assertIn('(int) $ownerId !== (int) $campaign->id', view)
        self.assertIn('$reviewItems->isNotEmpty()', view)

    def test_product_option_chips_and_exact_sku_selection(self):
        blade = source('resources/views/storefront/pages/product.blade.php')
        js = source('public/frontend/js/storefront-catalog.js')
        self.assertIn('p61-choice-flex', blade)
        self.assertIn('type="radio" name="option_', blade)
        self.assertIn('id="p08-variant-id"', blade)
        self.assertNotIn('<select name="product_variant_id"', blade)
        self.assertIn('input[type="radio"][data-option-id]', js)
        self.assertIn('checkedSelection', js)
        self.assertIn('if (cartVariant) cartVariant.value = \'\';', js)
        self.assertIn('syncPurchase(Number(variant.stock) > 0', js)

    def test_delivery_radio_and_live_totals(self):
        blade = source('resources/views/storefront/pages/checkout.blade.php')
        js = source('public/frontend/js/storefront-next.js')
        self.assertIn('type="radio" name="shipping_area_id"', blade)
        self.assertIn('data-shipping-area data-shipping-cost', blade)
        self.assertIn("$$('[data-shipping-area]', checkout)", js)
        self.assertIn("option.addEventListener('change', updateEstimate)", js)
        self.assertIn('subtotalPaisa + shippingPaisa', js)

    def test_menu_opens_existing_accessible_drawer(self):
        nav = source('resources/views/storefront/partials/navigation.blade.php')
        js = source('public/frontend/js/storefront-next.js')
        css = source('public/frontend/css/storefront-p61.css')
        self.assertIn('data-menu-open', nav)
        self.assertIn('data-menu-drawer role="dialog" aria-modal="true"', nav)
        self.assertIn('showDrawer(menu, button)', js)
        self.assertIn("aria-expanded', 'false'", js)
        self.assertIn('display:grid!important', css)

    def test_home_card_action_links_and_safe_variants(self):
        for file in ('resources/views/storefront/partials/home-product-card.blade.php',
                     'resources/views/storefront/partials/product-card.blade.php'):
            blade = source(file)
            for fragment in ('data-async-cart', 'name="intent" value="cart"',
                             'name="intent" value="checkout"', 'p61-quick-variants',
                             'name="product_variant_id"', '$productUrl'):
                self.assertIn(fragment, blade)
            self.assertIn('required', blade)
        js = source('public/frontend/js/storefront-next.js')
        self.assertIn('data.set(\'intent\', submitter?.value || \'cart\')', js)
        self.assertIn('if (result.redirect)', js)

if __name__ == '__main__':
    unittest.main(verbosity=2)
