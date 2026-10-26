/** P19 read-only security/UI source contracts. No database, no framework bootstrap and no file writes. */
import fs from 'node:fs';
import path from 'node:path';
import {createHash} from 'node:crypto';
const root = path.resolve(import.meta.dirname, '../..');
const source = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const files = {
  routes:source('routes/storefront.php'), user:source('routes/user.php'),
  types:source('app/Support/HomeSectionSchema.php'),
  builder:source('app/Services/Storefront/HomepageBuilder.php'),
  controller:source('app/Http/Controllers/Admin/HomepageBuilderController.php'),
  editor:source('resources/views/admin/storefront/homepage.blade.php'),
  shelf:source('app/Services/Storefront/CategoryShelf.php'),
  home:source('resources/views/storefront/pages/home.blade.php'),
  nav:source('resources/views/storefront/partials/navigation.blade.php'),
  cart:source('app/Http/Controllers/Frontend/StorefrontCartController.php'),
  form:source('resources/views/storefront/pages/checkout.blade.php'),
  buy:source('resources/views/storefront/pages/product.blade.php'),
  guest:source('app/Services/Checkout/StorefrontCheckout.php'),
  account:source('app/Http/Controllers/Frontend/StorefrontAccountController.php'),
  migration:source('database/migrations/2026_10_26_100001_create_storefront_accounts.php'),
  auth:source('config/auth.php'),
  model:source('app/Models/StorefrontAccount.php'),
  footer:source('resources/views/storefront/partials/footer.blade.php'),
  help:source('resources/views/storefront/pages/help.blade.php'),
  contact:source('resources/views/storefront/pages/contact.blade.php'),
  css:source('public/frontend/css/storefront-next.css'),
  js:source('public/frontend/js/storefront-next.js'),
  product:source('resources/views/storefront/partials/product-card.blade.php'),
  productHome:source('resources/views/storefront/partials/home-product-card.blade.php'),
  catalog:source('app/Services/Storefront/PublicCatalog.php'),
};
const cases = [
 ['Campaign homepage untouched: storefront root is separately prefixed', () =>
   files.user.includes("Route::get('/', [HomeController::class, 'index']") && files.routes.includes("Route::prefix('storefront')")],
 ['Builder type is explicit code-owned category shelf', () => files.types.includes("'category_products' => 'Category-wise Products'") && files.builder.includes("$section->type === 'category_products'")],
 ['Category section admin validates source, limit, order and optimistic version', () =>
   files.controller.includes("'source_category_id' => ['nullable', 'integer', 'min:1']")
   && files.controller.includes("'expected_version' => ['required', 'integer', 'min:1']")
   && files.editor.includes('source_category_id') && files.editor.includes('include_descendants')],
 ['Category shelf uses only eligible live products, sorted and bounded', () =>
   files.shelf.includes('eligibleProducts()') && files.shelf.includes('eligibleCategories()')
   && files.shelf.includes('max(1, min(24,') && !files.shelf.includes('DB::table(\'orders\')')],
 ['Category source parent active checked at edit and publish', () =>
   files.builder.includes("whereNull('parent_id')->select('id')")
   && files.builder.includes("$category->parent_id !== null && ! DB::table('categories')")],
 ['Hero supports multiple approved CMS images and carousel controls', () =>
   files.home.includes('data-hero-slide') && files.home.includes('data-hero-prev')
   && files.home.includes('getUrl()') && files.builder.includes("'hero_slide' ? 20")],
 ['Carousel has keyboard navigation/pause/reduced motion', () =>
   files.js.includes("event.key === 'ArrowRight'") && files.js.includes('mouseenter')
   && files.js.includes('prefers-reduced-motion')],
 ['Nested desktop and offcanvas mobile category navigation', () =>
   files.nav.includes('p19-mega-menu') && files.nav.includes('p19-mobile-category')
   && files.nav.includes('data-menu-drawer') && files.nav.includes('data-menu-open')],
 ['Drawer has escape, focus trap and focus return', () =>
   files.js.includes("event.key === 'Escape'") && files.js.includes("event.key !== 'Tab'")
   && files.js.includes('focusReturn?.focus?.()')],
 ['Floating cart count and subtotal read real authorized cart state', () =>
   files.cart.includes('array_sum(array_column') && files.cart.includes("'subtotal' => $state['subtotal']")
   && files.nav.includes('data-cart-total') && files.nav.includes('data-cart-count')
   && files.js.includes('updateCartTotals(data)') && files.js.includes('credentials:\'same-origin\'')],
 ['Cart writes retain CSRF and optimistic cart versions', () =>
   files.productHome.includes('@csrf') && files.product.includes('@csrf')
   && files.cart.includes("'cart_version' => ['required', 'integer'")
   && files.js.includes("'X-CSRF-TOKEN':csrf")],
 ['Product cards have reviewed 25/75 action, gated backend routing', () =>
   files.product.includes('data-async-cart') && files.productHome.includes('sf-product-actions')
   && files.css.includes('sf-product-quickform')],
 ['Desktop 4/mobile 2 columns maintained across product cards', () =>
   files.css.includes('repeat(4,minmax(0,1fr))') && files.css.includes('repeat(2,minmax(0,1fr))')],
 ['Two-column product detail keeps description below action', () =>
   files.buy.indexOf('p19-primary-buy-actions') < files.buy.indexOf('p19-product-benefits')
   && files.css.includes('.p07-detail{grid-template-columns:minmax(0,1fr) minmax(0,1fr)')],
 ['Sticky Order Now uses same protected product form and mobile overlap offsets', () =>
   files.buy.includes('data-sticky-order') && files.buy.includes('form="p08-product-order-form"')
   && files.css.includes('bottom:calc(66px + env(safe-area-inset-bottom))')],
 ['Checkout keeps one real POST COD form, 4 UI stages and server price authority', () =>
   files.form.includes("route('storefront.checkout.place')") && files.form.includes('@csrf')
   && files.form.includes("'Shipping','Payment','Review','Order Placed'")
   && files.form.includes('The final price, stock and shipping charge are recalculated')
   && files.guest.includes("Order::PAYMENT_COD")],
 ['Payment UI does not enable fictitious gateway writes', () =>
   files.form.includes('Cash on Delivery') && !files.form.includes('name="payment_method"')
   && !/Route::post\(['"]\/payment\//.test(files.routes)],
 ['Account admin guard separated from registered customers', () =>
   files.auth.includes("'storefront' => [") && files.auth.includes("'storefront_accounts' => [")
   && files.model.includes('extends Authenticatable')],
 ['Customer registration validates 12+ password, email uniqueness and rate limits', () =>
   files.account.includes('Password::min(12)->letters()->numbers()')
   && files.account.includes("'unique:storefront_accounts,email'")
   && files.routes.includes("middleware('throttle:5,1')")],
 ['Customer profile/order ownership and guarded schema', () =>
   files.account.includes('$customer->orders()') && files.account.includes('assertAccountReady')
   && files.account.includes("Schema::hasTable('storefront_accounts')")],
 ['Customer migration is forward-only and requires reviewed local DB', () =>
   files.migration.includes('P02MigrationSafety::assertApprovedP02Up()')
   && files.migration.includes("Schema::create('storefront_accounts'")
   && files.migration.includes("'storefront_account_id'") && files.migration.includes('throw new RuntimeException')],
 ['Server associates verified customer only, guest remains possible', () =>
   files.guest.includes("Auth::guard('storefront')->id()")],
 ['Footer has real link groups and validated admin-configured contacts', () =>
   files.footer.includes('Quick Links') && files.footer.includes('Customer Care')
   && files.footer.includes('Contact Us') && files.footer.includes('StorefrontContactLinks::class')],
 ['FAQ shows only global active content with keyboard-native accordion', () =>
   files.help.includes('<details') && source('app/Http/Controllers/Frontend/StorefrontCatalogController.php').includes("whereNull('campaign_id')->active()->sorted()")],
 ['UI animations include no-JS/reduced-motion and hide offscreen state', () =>
   files.css.includes('[hidden]{display:none!important}') && files.css.includes('prefers-reduced-motion:reduce')
   && files.js.includes('IntersectionObserver')],
 ['Reviews use stored actual rating, not hard-coded five stars', () =>
   files.home.includes('(int)$review->rating') && files.home.includes('data-review-carousel')],
 ['Storefront public activation still denied without isolated DB approval', () =>
   files.catalog.includes('P02MigrationSafety::assertIsolatedTestDatabase()') &&
   files.catalog.includes("config('storefront.p07_catalog_test_enabled', false) === true")],
 ['Never make web-auth/customer data accessible by public account routes', () =>
   files.account.includes("Auth::guard('storefront')->") && !files.account.includes("Auth::guard('web')")],
 ['No dangerous reset, schema drop, or hardcoded customer credentials in reviewed addition', () =>
   ![files.account,files.migration,files.guest,files.shelf].some(x => /artisan\s+(migrate:fresh|db:wipe)|DB::statement\(['"]DROP\s|password\s*=\s*['"]123/.test(x))],
];
let passed = 0;
for (const [label, test] of cases) {
 let ok = false;try {ok = Boolean(test());} catch {}
 console.log(`${ok?'PASS':'FAIL'} ${label}`);if(ok) passed++;
}
console.log(`RESULT: ${passed}/${cases.length} P19 storefront/security source contracts ${passed===cases.length?'PASS':'FAIL'} (no Laravel/MySQL/browser).`);
if(passed!==cases.length) process.exitCode=1;
