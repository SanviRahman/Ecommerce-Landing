"""Read-only source contract checks. Does NOT replace Laravel/MySQL integration."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]

def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

class P48Contract(unittest.TestCase):
    def test_activation_route_post_and_csrf(self):
        routes = read('routes/admin.php')
        self.assertIn("Route::post('/{product}/variants/activate'", routes)
        self.assertIn("Route::post('/{product}/variants/pause'", routes)
        page = read('resources/views/admin/products/variants.blade.php')
        self.assertIn("route('admin.products.variants.activate'", page)
        self.assertIn('@csrf', page)
        self.assertIn('Validate &amp; Activate', page)

    def test_checkout_guards_remain_fail_closed(self):
        config = read('config/storefront.php')
        self.assertIn("'p03_core_test_enabled' => false", config)
        self.assertIn("'p08_checkout_test_enabled' => env(", config)
        access = read('app/Support/P32LocalStorefrontAccess.php')
        for marker in ('assertActualMainDatabase','checkoutReady',"$port === 8000"):
            self.assertIn(marker, access)
        service=read('app/Services/Catalog/VariableStorefrontActivation.php')
        self.assertIn('P33CatalogAdminAccess::assertAllowed()',service)
        self.assertIn('$access->assertActualMainDatabase()',service)
        self.assertIn('$access->checkoutReady()',service)

    def test_invalid_or_missing_variant_never_force_enabled(self):
        page=read('resources/views/storefront/pages/product.blade.php')
        self.assertIn('@disabled(!$canSelectSku)',page)
        catalog=read('public/frontend/js/storefront-catalog.js')
        self.assertIn('available.length === 1',catalog)
        self.assertIn('Number(row.stock) > 0 && Number(row.price) > 0',catalog)
        self.assertIn('syncPurchase(false)',catalog)
        self.assertIn('picker.dataset.p35ManagedProduct',catalog)

    def test_stock_and_price_are_not_client_authoritative(self):
        checkout=read('app/Services/Checkout/StorefrontCheckout.php')
        allocator=read('app/Services/Inventory/InventoryAllocator.php')
        self.assertIn('allocateStorefrontOrder',checkout)
        self.assertIn("'product_variant_id' => $variant->id",checkout)
        self.assertIn('lockForUpdate()',allocator)
        self.assertIn("->where('stock', '>=', $quantity)->decrement('stock', $quantity)",allocator)

    def test_active_products_require_readiness_and_preserve_edit(self):
        service=read('app/Services/Catalog/VariableStorefrontActivation.php')
        for marker in ('product_option_assignments','VariantSignature::fromOptionValues','inventory_movements','stock_after','price_override','campaign_product','DB::transaction'):
            self.assertIn(marker,service)
        controller=read('app/Http/Controllers/Admin/ProductController.php')
        self.assertIn('->verifyActive($locked);',controller)
        self.assertIn("! $locked->status && $request->boolean('status')",controller)
        variants=read('app/Services/Catalog/VariantCatalogManager.php')
        self.assertIn('Pause this variable product before changing its assigned option dimensions',variants)

if __name__=='__main__':
    unittest.main(verbosity=2)
