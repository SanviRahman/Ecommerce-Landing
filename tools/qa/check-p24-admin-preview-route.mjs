/* P24 read-only source regression: screenshot's missing /admin/storefront alias.
   Never reads .env or connects to MySQL. */
import fs from 'node:fs';
const get = file => fs.readFileSync(file, 'utf8');
const admin = get('routes/admin.php');
const controller = get('app/Http/Controllers/Admin/HomepageBuilderController.php');
const user = get('routes/user.php');
const routes = get('routes/storefront.php');
const home = get('resources/views/storefront/pages/home.blade.php');
const styles = get('public/frontend/css/storefront-preview.css');
const index = get('resources/views/admin/storefront/homepage.blade.php');
const checks = [
  ['Admin prefix is inherited, login and admin roles retained', () =>
      get('routes/web.php').includes("Route::prefix('admin')")
      && admin.includes("Route::middleware(['auth', 'role:admin,employee'])")],
  ['Missing /admin/storefront uses explicit GET alias under admin and is role:admin only', () =>
      admin.includes("Route::get('/storefront', [HomepageBuilderController::class, 'entry'])")
      && /Route::get\('\/storefront',[^\n]+\n\s*->middleware\('role:admin'\)/.test(admin)],
  ['Entry resolves only to preexisting guarded preview / editor', () =>
      controller.includes('public function entry(')
      && controller.includes('$this->adminOnly($builder)')
      && controller.includes("'admin.storefront.home.preview'")
      && controller.includes("'admin.storefront.home.index'")],
  ['No direct entry action renders public checkout or disables authorization', () =>
      !/entry\([^)]*\)[\s\S]{0,570}config\(\s*['\"]storefront\.p08/.test(controller)
      && !/Route::redirect\(\s*['\"]\/admin\/storefront/.test(admin)],
  ['Campaign / remains bound to legacy controller; no new root /storefront alias', () =>
      user.includes("Route::get('/', [HomeController::class, 'index'])")
      && !/Route::(?:get|redirect)\('\/storefront'/.test(routes)
      && routes.includes("Route::get('/home', [StorefrontCatalogController::class, 'home'])")],
  ['Catalog always enforces independent isolation gate', () =>
      get('app/Services/Storefront/PublicCatalog.php').includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['Preview nav links to on-page real section IDs instead of fake interactive shop', () =>
      home.includes('href="#main"') && home.includes('href="{{ $previewProductsAnchor }}"')
      && home.includes('href="{{ $previewCategoriesAnchor }}"')
      && home.includes('id="sf-section-{{ $section[\'section_key\'] }}"')],
  ['Preview category images use known existing SVG fallback', () =>
      home.includes("$cat->getFirstMediaUrl('category_image') ?: asset('frontend/images/no-image.svg')")
      && get('resources/views/storefront/partials/home-product-card.blade.php').includes('data-image-fallback=')
      && home.includes("asset('frontend/js/image-fallback.js')")
      && fs.existsSync('public/frontend/images/no-image.svg')],
  ['Preview has honest non-shopping footer with real contact data', () =>
      home.includes("@include('storefront.preview.footer'")
      && fs.existsSync('resources/views/storefront/preview/footer.blade.php')
      && get('resources/views/storefront/preview/footer.blade.php').includes('StorefrontContactLinks')],
  ['Admin Builder existing preview button still bound to guarded route', () =>
      index.includes("route('admin.storefront.home.preview')")],
  ['Read-only preview retains inert commerce action and tags', () =>
      get('resources/views/storefront/partials/home-product-card.blade.php').includes('<span class=\"sf-cta sf-inert\">Order Now</span>')
      && home.includes('Design preview · Not accepting orders')],
  ['New preview anchor styles are accessible', () =>
      styles.includes('.sf-header nav a:focus-visible')],
];
let ok=0;
for(const [label, fn] of checks){let result=false;try{result=fn();}catch{}console.log(`${result?'PASS':'FAIL'} ${label}`);if(result)ok++;}
console.log(`RESULT: ${ok}/${checks.length} P24 admin storefront route+preview checks ${ok===checks.length?'PASS':'FAIL'} (offline only)`);
if(ok!==checks.length) process.exitCode=1;
