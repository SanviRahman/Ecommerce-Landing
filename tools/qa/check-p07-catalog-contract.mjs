import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const read = (file) => {
  const target = path.join(root, file);
  if (!fs.existsSync(target)) {
    console.error(`BLOCKED: Required P07 source file missing: ${file}. Re-extract the corrected P07 patch into the website root.`);
    process.exit(2);
  }
  return fs.readFileSync(target, 'utf8');
};
const file = {
  routes: read('routes/storefront.php'), user: read('routes/user.php'), web: read('routes/web.php'),
  service: read('app/Services/Storefront/PublicCatalog.php'),
  controller: read('app/Http/Controllers/Frontend/StorefrontCatalogController.php'),
  config: read('config/storefront.php'), published: read('resources/views/storefront/pages/home.blade.php'),
  preview: read('resources/views/storefront/preview/home.blade.php'),
  shop: read('resources/views/storefront/pages/shop.blade.php'),
  product: read('resources/views/storefront/pages/product.blade.php'),
  nav: read('resources/views/storefront/partials/navigation.blade.php'),
  css: read('public/frontend/css/storefront-catalog.css'), js: read('public/frontend/js/storefront-catalog.js'),
  phpunit: read('phpunit.p07.xml'), suite: read('tests/Feature/Storefront/P07PublicCatalogMySqlTest.php'),
  runner: read('tools/qa/p07-mysql-catalog-smoke.php'),
  cartMigration: read('database/migrations/2026_10_24_210011_p02_create_guest_carts.php'),
};
const tests = [
  ['Legacy root route untouched', () => /Route::get\('\/'\s*,\s*\[HomeController::class, 'index'\]\)/.test(file.user)],
  ['Legacy custom campaign GET/POST untouched', () => file.web.includes("->name('campaign.custom.show')") && file.web.includes("->name('campaign.custom.order.store')")],
  ['All new storefront routes are multi-segment', () => file.routes.includes("Route::prefix('storefront')") && !/get\('\/'/.test(file.routes)],
  ['Catalog HOME/SHOP/CATEGORY/PRODUCT retain GET routes', () => ['home', 'shop', 'category', 'product'].every((name) => file.routes.includes("->name('" + name + "')")) && file.routes.includes("Route::post('/cart/items'") && !file.controller.includes('DB::table(\'carts\')')],
  ['Storefront routes loaded before one-segment wildcard', () => file.user.includes("require __DIR__ . '/storefront.php'")],
  ['Feature off by default with env-gated test opt-in', () => file.config.includes("'p07_catalog_test_enabled' => env('P07_CATALOG_TEST_ENABLED', false)")],
  ['Independent P02 restricted local DB identity gate', () => file.service.includes('P02MigrationSafety::assertIsolatedTestDatabase()') && file.service.includes("app()->environment(['local', 'testing'])")],
  ['Every controller action checks catalog gate', () => (file.controller.match(/\$catalog->assertAvailable\(\)/g) || []).length === 4],
  ['Published revision FK and explicit status check', () => file.controller.includes('published_revision_id') && file.controller.includes("$revision['status'] === 'published'")],
  ['Draft controller/view is not reused via public preview endpoint', () => !file.controller.includes('->inspect(') && file.preview.includes('isPreview')],
  ['Public products require active category and parent', () => file.service.includes("->whereHas('category'") && file.service.includes("->orWhereHas('parent'")],
  ['No deleted variants or options shown', () => file.service.includes('variant->trashed()') && file.service.includes('! $value->is_active') && file.service.includes('! $value->option->is_active')],
  ['Variants rejected without exact assigned option cardinality', () => file.service.includes('$values->count() !== count($assignedOptionIds)')],
  ['User input sort mode allowlisted', () => file.controller.includes('in:newest,price_asc,price_desc,name')],
  ['User search has a maximum length and query binding', () => file.controller.includes("'max:80'") && file.controller.includes("->where('products.name', 'like'")],
  ['Sort tied deterministically to product id', () => file.service.includes("->orderBy('products.id')")],
  ['Staged pages are noindex and no-store', () => file.controller.includes("'private, no-store, max-age=0'") && file.controller.includes("'noindex, nofollow'")],
  ['No order/cart write or migration in public controller', () => !/\b(insert|update|delete|truncate|migrate|order\.store|checkout)\s*\(/i.test(file.controller)],
  ['Search input has real GET form and accessible label', () => file.nav.includes('method="GET"') && file.nav.includes('role="search"') && file.nav.includes('for="p07-header-search"')],
  ['Desktop category navigation has real active child links', () => file.nav.includes('@foreach($parent->children as $child)') && file.nav.includes("route('storefront.category'" )],
  ['Catalog cards are 4 desktop 2 mobile', () => file.css.includes('grid-template-columns:repeat(4,minmax(0,1fr))') && file.css.includes('grid-template-columns:repeat(2,minmax(0,1fr))')],
  ['Mobile nav is sticky, no fabricated cart button', () => file.nav.includes('p07-mobile-nav') && file.css.includes('position:fixed;bottom:0') && !/\b(?:Add to Cart|Buy Now)\b/.test(file.nav)],
  ['Product gallery uses server-rendered media path', () => file.product.includes("getMedia('product_gallery')") && file.js.includes('dataset.gallerySrc')],
  ['Variant selection matrix safely encoded and no unsafe HTML', () => file.product.includes('JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT') && !/innerHTML\s*=/.test(file.js)],
  ['P07 browsing notice remains when P08 cart disabled', () => file.product.includes('@if($cartVersion !== null)') && file.product.includes('Browsing preview') && file.config.includes("'p08_cart_test_enabled' => env('P08_CART_TEST_ENABLED', false)")],
  ['P05 private preview retains inert actions', () => file.published.includes('sf-inert') && file.preview.includes('isPreview')],
  ['P07 tests use rollback-only DatabaseTransactions', () => file.suite.includes('use DatabaseTransactions;') && file.suite.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['P07 launcher requires separate explicit consent', () => file.runner.includes('P07_TEST_WRITE_APPROVED') && file.runner.includes('YES_ROLLBACK_ONLY')],
  ['Preboot rejects existing business/homepage data', () => file.runner.includes("'storefront_pages'") && file.runner.includes("'orders'") && file.runner.includes("'products'")],
  ['No migration/reset/seed operations in launcher', () => !/artisan\s+(?:migrate|db:|seed)|migrate:fresh|truncate\s*\(/i.test(file.runner)],
  ['Runner selects P07 PHPUnit suite only', () => file.runner.includes('--filter=P07PublicCatalogMySqlTest') && file.phpunit.includes('P07PublicCatalogMySqlTest.php')],
  ['P02 migration creates carts and cart_items, not a guest_carts table', () => file.cartMigration.includes("Schema::create('carts'") && file.cartMigration.includes("Schema::create('cart_items'") && !file.cartMigration.includes("Schema::create('guest_carts'")],
  ['P07 smoke preflight checks real carts and cart_items tables', () => file.runner.includes("'carts'") && file.runner.includes("'cart_items'") && !/\bguest_carts\b/.test(file.runner)],
  ['P07 PHPUnit asserts both real cart tables remain empty', () => file.suite.includes("DB::table('carts')") && file.suite.includes("DB::table('cart_items')") && !/\bguest_carts\b/.test(file.suite)],
];
let passed = 0;
for (const [name, fn] of tests) {
  const result = (() => { try { return Boolean(fn()); } catch { return false; } })();
  console.log(`${result ? 'PASS' : 'FAIL'} ${name}`);
  if (result) passed += 1;
}
console.log(`RESULT: ${passed}/${tests.length} P07 source contracts ${passed === tests.length ? 'PASS' : 'FAIL'} (no DB access)`);
if (passed !== tests.length) process.exitCode = 1;
