/** P26 fail-first source contracts: diagnostics and isolated commerce opt-in. No Laravel/database. */
import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const get = f => fs.existsSync(path.join(root, f)) ? fs.readFileSync(path.join(root, f), 'utf8') : '';
const gate=get('app/Support/LocalStorefrontBrowseAccess.php');
const admin=get('routes/admin.php');
const diag=get('app/Http/Controllers/Admin/StorefrontReadinessController.php');
const view=get('resources/views/admin/storefront/readiness.blade.php');
const middleware=get('app/Http/Middleware/P26IsolatedCommerceQaMiddleware.php');
const access=get('app/Support/P26IsolatedCommerceQaAccess.php');
const routes=get('routes/storefront.php');
const config=get('config/storefront.php');
const observer=get('app/Observers/OrderBidirectionalSyncObserver.php');
const runner=get('tools/qa/p26-isolated-sandbox.ps1');
const checks=[
 ['Homepage and campaign routes remain unchanged',()=>routes.includes("Route::get('/home', [StorefrontCatalogController::class, 'home'])") && get('routes/user.php').includes("Route::get('/', [HomeController::class, 'index'])")],
 ['Readiness page is admin role gated',()=>admin.includes('StorefrontReadinessController') && admin.includes("->middleware('role:admin')->name('storefront.readiness')")],
 ['Showroom opt-in uses authenticated CSRF-protected POST, never GET',()=>admin.includes("Route::post('/storefront/readiness/enable'") && admin.includes("Route::post('/storefront/readiness/disable'") && view.includes('@csrf')],
 ['Active Admin main-DB 404 offers local readiness, guests still denied',()=>middleware.includes("$request->routeIs('storefront.home')") && middleware.includes('LocalHomepageAccess::DATABASE') && middleware.includes("Auth::guard('web')->user()") && middleware.includes('LocalStorefrontBrowseAccess::class') && middleware.includes("redirect()->route('admin.storefront.readiness')")],
 ['Showroom session grant is explicit and revocable',()=>gate.includes("session()->get('p26_local_storefront_preview_approved')") && diag.includes("SESSION_KEY = 'p26_local_storefront_preview_approved'") && diag.includes("session()->put(self::SESSION_KEY, true)") && diag.includes("session()->forget(self::SESSION_KEY)")],
 ['Showroom retains strict verified builder DB identity',()=>gate.includes('LocalHomepageAccess::assertActualBuilderDatabase()') && diag.includes('LocalHomepageAccess::assertActualBuilderDatabase()')],
 ['Readiness reports admin-safe facts only and not secrets',()=>diag.includes('configuredForBuilder') && view.includes('P14_LOCAL_HOMEPAGE_BUILDER_ENABLED') && !view.includes('DB_PASSWORD')],
 ['Isolated full-commerce opt-in remains default off',()=>config.includes("'p26_isolated_commerce_qa_enabled' => env('P26_ISOLATED_COMMERCE_QA_ENABLED', false)")],
 ['Full-commerce is restricted to local loopback 8088',()=>access.includes("$environment === 'local'") && access.includes('$port === 8088') && access.includes("'127.0.0.1'")],
 ['Full-commerce verifies actual isolated MySQL identity',()=>access.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
 ['Full-commerce middleware restricted to storefront prefix',()=>routes.includes('P26IsolatedCommerceQaMiddleware::class') && routes.includes("Route::prefix('storefront')")],
 ['Commerce flags only within approved request and restored after',()=>middleware.includes("'storefront.p03_core_test_enabled'") && middleware.includes("config()->set($key, true)") && middleware.includes("config()->set($old)") && middleware.includes('finally')],
 ['P03 core and legacy bridge stay disabled at baseline',()=>config.includes("'p03_core_test_enabled' => false") && config.includes("'p03_order_writer_test_enabled' => false")],
 ['Legacy P02 transaction/ownership guard unchanged',()=>get('app/Services/Cart/GuestCartService.php').includes('P02MigrationSafety::assertIsolatedTestDatabase()') && get('app/Services/Inventory/InventoryAllocator.php').includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
 ['Sandbox does not run migrations, seeds, wipes or config writes',()=>runner.includes('p02-mysql-preflight.php') && runner.includes('p26-isolated-schema-preflight.php') && !/(artisan\s+(migrate|db:seed|key:generate|config:clear)|migrate:fresh)/i.test(runner)],
 ['Sandbox isolates database and session cookies',()=>runner.includes('p01_campaign_test') && runner.includes('p01_tester') && runner.includes('p26_storefront_qa_session')],
 ['Sandbox never turns on existing main DB write mode',()=>access.includes('P02MigrationSafety::assertIsolatedTestDatabase()') && !config.includes('P26_ENABLE_ORIGINAL_DB_CHECKOUT')],
 ['QA access enforces restricted MySQL grants, not just DB name',()=>access.includes("DB::select('SHOW GRANTS')") && access.includes('hasRestrictedGrants()') && access.includes('GRANT ALL PRIVILEGES')],
 ['QA and normal cart use distinct owner cookies',()=>get('app/Services/Cart/GuestCartOwner.php').includes('p26_storefront_guest_owner_v1') && get('app/Services/Cart/GuestCartOwner.php').includes("$request->attributes->get('p26_isolated_commerce_qa'")],
 ['QA storefront checkout cannot sync to external sites',()=>observer.includes("request()->attributes->get('p26_isolated_commerce_qa'")],
 ['No new customer data in reference patch',()=>!get('tools/qa/p26-isolated-sandbox.ps1').includes('INSERT INTO')],
];
let passed=0;
for (const [name,check] of checks){let ok=false;try {ok=!!check();}catch{}console.log(`${ok?'PASS':'FAIL'} ${name}`);if(ok)passed++;}
console.log(`RESULT: ${passed}/${checks.length} P26 activation contracts ${passed===checks.length?'PASS':'FAIL'} (source-only)`);
if(passed!==checks.length) process.exitCode=1;
