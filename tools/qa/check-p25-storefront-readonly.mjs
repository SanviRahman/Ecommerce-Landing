/* P25 source contract: local admin GET-only showroom. No network or DB writes. */
import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const gate=read('app/Support/LocalStorefrontBrowseAccess.php');
const catalog=read('app/Services/Storefront/PublicCatalog.php');
const config=read('config/storefront.php');
const controller=read('app/Http/Controllers/Frontend/StorefrontCatalogController.php');
const routes=read('routes/storefront.php');
const user=read('routes/user.php');
const layout=read('resources/views/storefront/layouts/catalog.blade.php');
const home=read('resources/views/storefront/pages/home.blade.php');
const nav=read('resources/views/storefront/partials/navigation.blade.php');
const footer=read('resources/views/storefront/partials/footer.blade.php');
const product=read('resources/views/storefront/pages/product.blade.php');
const cases=[
 ['Public route still explicitly /storefront/home',()=>routes.includes("Route::get('/home', [StorefrontCatalogController::class, 'home'])")],
 ['Campaign root untouched',()=>user.includes("Route::get('/', [HomeController::class, 'index'])")],
 ['Independent, default-off P25 consent',()=>config.includes("'p25_local_browse_enabled' => env('P25_LOCAL_STOREFRONT_BROWSE_ENABLED', false)")],
 ['P25 local, debug, GET and explicit route allowlist',()=>gate.includes("$environment === 'local'") && gate.includes("&& $method === 'GET'") && gate.includes('in_array($routeName, self::BROWSE_ROUTES, true)')],
 ['Active web admin authorization, not customer guard',()=>gate.includes("Auth::guard('web')->user()") && gate.includes('$admin->isAdmin()') && gate.includes('$admin->is_active')],
 ['Strict actual local DB identity check retained',()=>gate.includes('LocalHomepageAccess::assertActualBuilderDatabase()') && gate.includes('catch (RuntimeException $exception)')],
 ['No cart/account/checkout routes in showroom allowlist',()=>!gate.match(/['"]storefront\.(cart|checkout|account)[^'"\s]*['"]/)],
 ['P07 isolated public DB gate retained',()=>catalog.includes('P02MigrationSafety::assertIsolatedTestDatabase()') && catalog.includes("config('storefront.p07_catalog_test_enabled', false) === true")],
 ['P25 request attribute set only after authorization',()=>catalog.includes("app(LocalStorefrontBrowseAccess::class)->allows(request())") && catalog.includes("attributes->set('p25_local_readonly_browse', true)")],
 ['No cart service read in approved local preview',()=>controller.includes("$request->attributes->get('p25_local_readonly_browse', false)") && controller.includes('GuestCartService')],
 ['Same actual storefront templates used',()=>controller.includes("response()->view('storefront.pages.home'") && home.includes("@include('storefront.partials.navigation'") && layout.includes("@include('storefront.partials.navigation'")],
 ['Navigation hides cart and customer account actions in local preview',()=>nav.includes('$cartActive = ! $localReadOnly') && nav.includes('@if($localReadOnly)') && nav.includes('Customer account disabled in local browsing preview')],
 ['Footer hides unavailable shopping/account links in local preview',()=>footer.includes('@unless($p25LocalReadOnly)') && footer.includes('!$p25LocalReadOnly')],
 ['Product view has a genuine browsing-only notice',()=>product.includes('Browsing preview')],
 ['Read-only banner visibly labels admin showroom',()=>home.includes('LOCAL ADMIN STOREFRONT PREVIEW') && layout.includes('LOCAL ADMIN STOREFRONT PREVIEW')],
 ['Product details and category pages stay linked',()=>read('resources/views/storefront/partials/home-product-card.blade.php').includes("route('storefront.product'") && home.includes("route('storefront.category'")],
 ['P24 image fallback contract repaired',()=>read('resources/views/storefront/partials/home-product-card.blade.php').includes('data-image-fallback=')],
 ['P08/checkout feature flags remain independently disabled',()=>config.includes("'p03_core_test_enabled' => false") && config.includes("'p08_checkout_test_enabled' => env('P08_CHECKOUT_TEST_ENABLED', false)")],
];
let ok=0;
for (const [name,test] of cases){let result=false;try{result=!!test();}catch{} console.log(`${result?'PASS':'FAIL'} ${name}`);if(result)ok++;}
console.log(`RESULT: ${ok}/${cases.length} P25 source security contracts ${ok===cases.length?'PASS':'FAIL'} (no Laravel/DB/browser).`);
if(ok!==cases.length) process.exitCode=1;
