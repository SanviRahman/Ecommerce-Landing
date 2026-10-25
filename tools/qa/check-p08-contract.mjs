import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8');
const files = {
  config: read('config/storefront.php'), routes: read('routes/storefront.php'),
  cart: read('app/Services/Cart/GuestCartService.php'),
  owner: read('app/Services/Cart/GuestCartOwner.php'),
  checkout: read('app/Services/Checkout/StorefrontCheckout.php'),
  allocator: read('app/Services/Inventory/InventoryAllocator.php'),
  idem: read('app/Services/Checkout/CheckoutIdempotencyService.php'),
  controller: read('app/Http/Controllers/Frontend/StorefrontCartController.php'),
  catalog: read('app/Http/Controllers/Frontend/StorefrontCatalogController.php'),
  product: read('resources/views/storefront/pages/product.blade.php'),
  cartView: read('resources/views/storefront/pages/cart.blade.php'),
  checkoutView: read('resources/views/storefront/pages/checkout.blade.php'),
  nav: read('resources/views/storefront/partials/navigation.blade.php'),
  css: read('public/frontend/css/storefront-cart.css'),
  js: read('public/frontend/js/storefront-catalog.js'),
  runner: read('tools/qa/p08-mysql-cart-checkout-smoke.php'),
  suite: read('tests/Feature/Storefront/P08GuestCartCheckoutMySqlTest.php'),
  configXml: read('phpunit.p08.xml'),
  migration: read('database/migrations/2026_10_24_210011_p02_create_guest_carts.php'),
  userRoutes: read('routes/user.php'), rootRoutes: read('routes/web.php'),
};
const f = files;
const checks = [
  ['Both P08 flags remain OFF by default', () => ["'p08_cart_test_enabled' => env('P08_CART_TEST_ENABLED', false)","'p08_checkout_test_enabled' => env('P08_CHECKOUT_TEST_ENABLED', false)"].every(s => f.config.includes(s))],
  ['P03 core and existing writer stay OFF by default', () => f.config.includes("'p03_core_test_enabled' => false") && f.config.includes("'p03_order_writer_test_enabled' => false")],
  ['Independent P02 restricted DB guard on cart', () => f.cart.includes('P02MigrationSafety::assertIsolatedTestDatabase()') && f.cart.includes('$this->catalog->assertAvailable()')],
  ['P08 checkout independently gated with P03 core', () => f.checkout.includes('p08_checkout_test_enabled') && f.checkout.includes('p03_core_test_enabled')],
  ['No root or campaign route altered', () => f.userRoutes.includes("require __DIR__ . '/storefront.php'") && f.rootRoutes.includes("campaign.custom.show") && f.rootRoutes.includes("campaign.custom.order.store")],
  ['P08 routes are multi-segment and use throttle for mutating endpoints', () => f.routes.includes("Route::prefix('storefront')") && f.routes.includes("Route::post('/cart/items'") && f.routes.includes("Route::patch('/cart/items/{item}'") && f.routes.includes("Route::post('/checkout'") && (f.routes.match(/throttle:/g) || []).length >= 3],
  ['No GET mutations added', () => !/Route::get\([^\n]+\[(?:StorefrontCartController::class), '(?:add|change|place)'/.test(f.routes)],
  ['Cookie owner is 256-bit random and server hashed', () => f.owner.includes('bin2hex(random_bytes(32))') && f.owner.includes("hash('sha256', $token)")],
  ['Owner cookie is HttpOnly and Secure on HTTPS', () => f.owner.includes('$request->isSecure(), true, false') && f.owner.includes("'lax'") && f.owner.includes('Cookie::queue')],
  ['Cart API never receives raw cart owner/owner_hash as input', () => !f.controller.includes("input('owner_hash'") && !f.controller.includes("request->owner_hash")],
  ['Cart optimistic version is mandatory for add/change', () => f.controller.includes("'cart_version' => ['required', 'integer', 'min:0']") && f.controller.includes("'cart_version' => ['required', 'integer', 'min:1']")],
  ['Cart quantity bounded and max 30 lines', () => f.cart.includes('MAX_LINES = 30') && f.cart.includes('MAX_QUANTITY = 1000')],
  ['Cart scoping prevents cross-owner mutations', () => f.cart.includes("->where('cart_id', $cart->id)") && f.cart.includes("->where('owner_hash', $ownerHash)")],
  ['Expired cart cleaned only under transaction', () => f.cart.includes('Carbon::parse') && f.cart.includes('DB::transaction') && f.cart.includes('lockedCart')],
  ['Cart creates owner-scoped rows with version + unique variant', () => f.migration.includes("Schema::create('carts'") && f.migration.includes("Schema::create('cart_items'") && f.migration.includes('cart_variant_unique') && f.cart.includes("->increment('lock_version'")],
  ['Cart never deducts inventory or writes orders', () => !/\b(decrement\('stock'|inventory_movements|Order::create|order_items)\b/.test(f.cart)],
  ['Server-authoritative pricing only', () => f.cart.includes('price_override ?? $product->new_price') && !/quoted_unit_price.*\$request/.test(f.cart)],
  ['Variable variant completeness delegated to PublicCatalog', () => f.cart.includes('$this->catalog->eligibleVariants($product)')],
  ['Checkout requires authoritative cart and quote', () => f.checkout.includes('assertOwnedCart($ownerHash, $expected)') && f.checkout.includes('quoted_unit_price') && f.checkout.includes('price')],
  ['Checkout request identity and row replay are durable', () => f.checkout.includes('$this->idempotency->claim(') && f.checkout.includes('$this->idempotency->complete(') && f.idem.includes('lockForUpdate()')],
  ['Checkout uses same P03 allocator and ledger', () => f.checkout.includes('$this->inventory->allocateStorefrontOrder(') && f.allocator.includes('allocate:v1:item:')],
  ['Stock decrement conditional and inside allocator', () => f.allocator.includes("->where('stock', '>=', $quantity)->decrement('stock', $quantity)") && f.allocator.includes('allocateStorefrontOrder')],
  ['Storefront allocator validates variant signature and assigned options', () => f.allocator.includes('VariantSignature::fromOptionValues($selected)') && f.allocator.includes('$catalog->eligibleVariants($model)')],
  ['No silent price discount or override from browser', () => f.checkout.includes('$this->carts->price($product, $variant)') && !f.controller.includes("'price' =>")],
  ['Shipping rows scoped globally and active', () => f.checkout.includes('ShippingCharge::resolvedForCampaign(null)')],
  ['Only COD is supported', () => f.checkout.includes('Order::PAYMENT_COD') && f.checkout.includes('Order::PAYMENT_STATUS_COD_PENDING')],
  ['Existing block and customer identity reused', () => f.checkout.includes('$this->blocked->findActiveBlock(') && f.checkout.includes('$this->identities->resolveOrCreate(')],
  ['Employee assignment runs only in transaction', () => f.checkout.includes('$this->assignment->assign($order)') && f.checkout.includes('DB::transaction(function ()')],
  ['Cart clears only AFTER stock/idempotency complete', () => f.checkout.indexOf('$this->idempotency->complete(') < f.checkout.indexOf("DB::table('cart_items')->where('cart_id', $cart->id)->delete()")],
  ['Client checkout key remains same on form retry', () => f.checkoutView.includes("old('checkout_key', $checkoutKey)")],
  ['CSRF for cart add, update and checkout', () => f.product.includes('@csrf') && f.cartView.includes('@csrf') && f.checkoutView.includes('@csrf')],
  ['Product P08 forms appear only under explicit cart version gate', () => f.product.includes('@if($cartVersion !== null)') && f.product.includes('Browsing preview')],
  ['Available shipping dropdown is server-sourced', () => f.checkoutView.includes('@foreach($areas as $area)') && f.checkoutView.includes('$areas->isEmpty()')],
  ['Mobile submit precedes summary; desktop sticky', () => f.checkoutView.indexOf('p08-checkout-form') < f.checkoutView.indexOf('p08-checkout-summary') && f.css.includes('position:sticky')],
  ['25/75 add/cart vs order buttons present', () => f.css.includes('grid-template-columns:minmax(0,1fr) minmax(0,3fr)')],
  ['Checkout tests use DatabaseTransactions on named isolated MySQL', () => f.suite.includes('use DatabaseTransactions;') && f.suite.includes('P02MigrationSafety::assertIsolatedTestDatabase()') && f.configXml.includes('p01_campaign_test')],
  ['Smoke runner requires explicit rollback consent and prebootstrap', () => f.runner.includes("'P08_TEST_WRITE_APPROVED'") && f.runner.includes('tests/bootstrap-p03.php')],
  ['Smoke runner never migrates, resets or seeds', () => !/migrate:fresh|db:wipe|db:seed|artisan\s+.*migrate|truncate\s*\(/.test(f.runner)],
  ['Smoke runner filters exact PHPUnit test class', () => f.runner.includes('--filter=P08GuestCartCheckoutMySqlTest') && f.configXml.includes('P08GuestCartCheckoutMySqlTest.php')],
  ['No direct security-middleware bypass', () => !/withoutMiddleware|withoutExceptionHandling|disableCsrf/i.test(f.controller)],
  ['No raw dynamic HTML injection', () => !/innerHTML\s*=/.test(f.js) && !/\{!!/.test(f.cartView + f.checkoutView)],
];
let passed = 0;
for (const [name, test] of checks) {
  let ok = false;
  try { ok = Boolean(test()); } catch (_) { ok = false; }
  console.log((ok ? 'PASS ' : 'FAIL ') + name);
  if (ok) passed++;
}
console.log(`RESULT: ${passed}/${checks.length} P08 source/security contracts ${passed === checks.length ? 'PASS' : 'FAIL'} (static; no MySQL)`);
if (passed !== checks.length) process.exitCode = 1;
