import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const file = (p) => fs.readFileSync(path.join(root, p), 'utf8');
let count=0;
function ok(label, condition) {
  if (!condition) {console.error('FAIL ' + label); process.exitCode=1; return;}
  console.log('PASS '+label); count++;
}
const layout=file('resources/views/storefront/layouts/catalog.blade.php');
const home=file('resources/views/storefront/pages/home.blade.php');
const nav=file('resources/views/storefront/partials/navigation.blade.php');
const footer=file('resources/views/storefront/partials/footer.blade.php');
const product=file('resources/views/storefront/pages/product.blade.php');
const widget=file('resources/views/storefront/partials/contact-widget.blade.php');
const contacts=file('app/Support/StorefrontContactLinks.php');
const script=file('public/frontend/js/storefront-catalog.js');
const css=file('public/frontend/css/storefront-contact.css');
const cartcss=file('public/frontend/css/storefront-cart.css');
const userRoutes=file('routes/user.php');
const config=file('config/storefront.php');
const launch=file('tools/qa/p10-mysql-ui-smoke.php');
ok('Original root and campaign routes remain independent', userRoutes.includes("Route::get('/',") && userRoutes.includes("'/campaign/{campaign:slug}/order'") && userRoutes.includes("'/storefront.php'"));
ok('Staging feature flags remain disabled by default', config.includes("'p03_core_test_enabled' => false") && config.includes("'p08_cart_test_enabled' => env('P08_CART_TEST_ENABLED', false)"));
ok('Storefront catalog includes contact styling and widget', layout.includes('storefront-contact.css') && layout.includes("'storefront.partials.contact-widget'"));
ok('Published homepage includes same contact styling', home.includes('storefront-contact.css') && home.includes("'storefront.partials.contact-widget'"));
ok('Private revision preview never renders contact widget', /@if\(\$isPreview \?\? false\)[\s\S]*?@else[\s\S]*?@include\('storefront.partials.contact-widget'\)/.test(home) && home.includes('Private staging preview'));
ok('Cart shell and mobile action appear only with real cart feature flag',
  nav.includes("$cartActive = config('storefront.p08_cart_test_enabled', false) === true")
  && nav.includes('@if($cartActive)') && nav.includes('data-cart-summary-url')
  && nav.includes("route('storefront.cart.summary')"));
ok('Catalog footer has separate Quick Links, Customer Care, Contact Us',
  footer.includes('Quick Links') && footer.includes('Customer Care') && footer.includes('Contact Us'));
ok('Footer uses configured validated contact links, not placeholder destinations',
  footer.includes('StorefrontContactLinks::class') && !/href="#"|href="javascript:/.test(footer));
ok('Footer never implies ungated checkout is live', footer.includes('where checkout is enabled') === false
  && footer.includes('COD on supported orders'));
ok('Configured-only contact widget has no placeholder destinations', widget.includes('count($storefrontContactLinks) > 0') && !/href="#"|href="javascript:/.test(widget));
ok('Contact action URLs are escaped by Blade and external links isolated', widget.includes("{{ $contact['href'] }}") && widget.includes('noopener noreferrer'));
ok('Only live/active admin SiteSetting rows feed contacts', contacts.includes("SiteSetting::query()->where('status', true)"));
ok('WhatsApp uses normalized number, not freeform URL', contacts.includes("'https://wa.me/' . $digits") && contacts.includes("preg_replace('/\\D+/'"));
ok('Messenger domain is allowlisted and HTTPS-only', contacts.includes("'scheme'") && contacts.includes("'https'") && contacts.includes("'m.me'") && contacts.includes('isDirectMessenger'));
ok('Email and phone targets are validated', contacts.includes('FILTER_VALIDATE_EMAIL') && contacts.includes("'tel:' . $dial"));
ok('Product validation errors appear as an alert', product.includes('role="alert"') && product.includes('$errors->all()'));
ok('Product reselects remembered variation safely', product.includes("old('product_variant_id')") && script.includes('const remembered = data.variants.find'));
ok('Main and mobile order action submit the same real checkout form', product.includes('id="p08-product-order-form"') && product.includes('form="p08-product-order-form"') && product.includes('name="intent" value="checkout"'));
ok('Mobile order button only rendered with checkout/core flag', product.includes("config('storefront.p08_checkout_test_enabled'") && product.includes('p10-mobile-buy'));
ok('Mobile submit and contact dock avoid bottom navigation', cartcss.includes('bottom:calc(63px + env(safe-area-inset-bottom))') && css.includes('bottom:calc(78px + env(safe-area-inset-bottom))'));
ok('P10 runner gated to test schema and explicit consent', launch.includes('YES_ROLLBACK_ONLY') && launch.includes('p01_campaign_test') && launch.includes('P02TestSchemaGate::connectFromEnvironment'));
ok('P10 runner neither migrates nor resets database', !/artisan migrate|migrate:fresh|db:wipe|TRUNCATE|db:seed/i.test(launch));
ok('Legacy staging P09 lock-order contract still preserved', file('app/Services/Checkout/StorefrontCheckout.php').includes('lockForUpdate()'));
if (process.exitCode) process.exit(process.exitCode);
console.log(`RESULT: ${count}/${count} P10 UI/integration source contracts PASS (no DB).`);
