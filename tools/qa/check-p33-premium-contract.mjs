/** P33 strictly read-only source contracts. No Laravel boot, SQL, network or writes. */
import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const adminRoutes = read('routes/admin.php');
const userRoutes = read('routes/user.php');
const storefrontRoutes = read('routes/storefront.php');
const home = read('resources/views/storefront/pages/home.blade.php');
const layout = read('resources/views/storefront/layouts/catalog.blade.php');
const nav = read('resources/views/storefront/partials/navigation.blade.php');
const footer = read('resources/views/storefront/partials/footer.blade.php');
const adminHome = read('resources/views/admin/storefront/homepage.blade.php');
const builder = read('app/Services/Storefront/HomepageBuilder.php');
const heroController = read('app/Http/Controllers/Admin/HomepageBuilderController.php');
const animation = read('public/frontend/js/storefront-next.js');
const premium = read('public/frontend/js/storefront-premium.js');
const css = read('public/frontend/css/storefront-premium.css');
const product = read('app/Services/Catalog/VariantCatalogManager.php');
const localAdmin = read('app/Support/P33CatalogAdminAccess.php');
const catalog = read('app/Http/Controllers/Frontend/StorefrontCatalogController.php');
const branding = read('app/Support/StorefrontBranding.php');
const track = read('app/Http/Controllers/Frontend/OrderTrackingController.php');
const checks = [
  ['Owner-approved root storefront and isolated campaign routes', () => userRoutes.includes("Route::get('/', [StorefrontCatalogController::class, 'home'])") && userRoutes.includes("Route::get('/campaign/{campaign:slug}'")],
  ['Hero images submit inside existing admin role-gated group', () => adminRoutes.includes("Route::middleware('role:admin')->prefix('storefront/home')") && adminRoutes.includes("->whereUuid('sectionKey')->name('hero-images.store')")],
  ['Hero image form has CSRF, version, 1-6 multi upload', () => adminHome.includes('hero_images[]') && adminHome.includes('enctype="multipart/form-data"') && adminHome.includes('@csrf') && adminHome.includes('name="expected_version"')],
  ['Hero validates input and protects optimistic version', () => heroController.includes("'max:6'") && heroController.includes("'max:4096'") && heroController.includes("$builder->inspect()") && heroController.includes('abort(409')],
  ['Hero images are isolated from Site Logo', () => read('app/Models/SiteSetting.php').includes("addMediaCollection('storefront_hero_image')") && read('app/Models/SiteSetting.php').includes("addMediaCollection('site_logo')")],
  ['Hero publication is reviewed and single draft mutation', () => builder.includes('public function addHeroSlides(') && builder.includes("'hero_images_upload'") && builder.includes('storefront_hero_image') && builder.includes('draftSection(')],
  ['Failed uploads remove only newly created media', () => heroController.includes('foreach ($newMedia as $media)') && heroController.includes('$media->delete()')],
  ['Hero RTL/LTR slide track moves right-to-left automatically', () => home.includes('data-hero-track') && animation.includes('translate3d(-${active * 100}%') && animation.includes('setInterval(next, 5400)')],
  ['Hero keyboard/reduced motion and invisible-slide safety', () => animation.includes('event.key === \'ArrowRight\'') && animation.includes('clone.inert = true') && animation.includes('if (reduced || doc.hidden || timer)')],
  ['Auto shelves skip categories already featured by admin', () => catalog.includes('$configuredCategoryIds') && catalog.includes('in_array((int) $parent->id, $configuredCategoryIds, true)') && home.includes('data-product-carousel')],
  ['Category and products use actual active catalog queries', () => catalog.includes('eligibleCategories()') && catalog.includes('productsFor(') && catalog.includes('eligibleProducts()->count()')],
  ['Variant admin only on active verified local or isolated DB', () => localAdmin.includes('auth()->user()->isAdmin() && auth()->user()->is_active') && localAdmin.includes('assertActualMainDatabase()') && localAdmin.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['Legacy stock/history never silently converted on local main DB', () => product.includes("DB::table('order_items')") && product.includes("DB::table('campaign_product')") && product.includes("$stock !== 0")],
  ['Admin variant options remain real price/SKU/stock flows', () => read('resources/views/admin/products/variants.blade.php').includes('price_override') && read('resources/views/admin/products/partials/form.blade.php').includes("admin.products.options.index")],
  ['Branding uses active site configuration and allowlisted HTTPS links', () => branding.includes("where('status', true)") && branding.includes("social->platform_name") && branding.includes("$parts['scheme']") && branding.includes("=== 'https'") && branding.includes('in_array($platform, $allowed, true)')],
  ['Header and footer use configured logo', () => nav.includes('$branding->logo()') && footer.includes('$branding->logo()') && read('app/Models/SiteSetting.php').includes("'site_logo'")],
  ['Footer social icons use only allowed SVG', () => footer.includes("storefront.partials.social-icon") && branding.includes("'instagram'") && read('resources/views/storefront/partials/social-icon.blade.php').includes('<svg')],
  ['Header Account and Wishlist/Cart are adjacent with real counts', () => nav.includes('p33-account') && nav.includes('p33-wishlist') && nav.includes('p33-cart-link') && nav.includes('data-wishlist-count') && nav.includes('data-cart-count')],
  ['Cart counts fetched from existing server API only', () => nav.includes('data-cart-summary-url') && animation.includes('await fetch(cartEndpoint') && animation.includes('el.textContent = String(data.count)')],
  ['Floating mini-cart centered on all storefront layouts', () => nav.includes('class="p19-floating-cart"') && home.includes("partials.navigation") && layout.includes("partials.navigation") && css.includes('top:50%!important;bottom:auto!important')],
  ['Homepage tracking uses original real POST route with CSRF', () => home.includes("route('order.track.search')") && home.includes('data-storefront-track') && premium.includes('fetch(form.action') && track.includes('$this->ordersByPhone($phone)')],
  ['Standalone tracking route renders storefront layout', () => track.includes("view('storefront.pages.track-order'") && read('resources/views/storefront/pages/track-order.blade.php').includes("@extends('storefront.layouts.catalog')")],
  ['Premium animation has reduced-motion and scroll reveal', () => css.includes('@media(prefers-reduced-motion:reduce)') && animation.includes('IntersectionObserver') && css.includes('.p19-animated [data-reveal]')],
  ['Premium fonts CSS applies on both site layouts', () => layout.includes('fonts.googleapis.com') && home.includes('fonts.googleapis.com') && css.includes("font-family:'Manrope'")],
  ['Existing campaign order routes and CSRF are unchanged', () => userRoutes.includes("Route::post('/campaign/{campaign:slug}/order'") && read('routes/web.php').includes("require __DIR__ . '/user.php'")],
  ['No migrations, no DB reset added in this patch', () => !premium.includes('migrate:fresh') && !localAdmin.includes('Schema::create(') && !builder.includes('migrate:fresh')],
];
let ok = 0;
for (const [label, test] of checks) {
  let passed = false;
  try {passed = test();} catch {passed = false;}
  console.log(`${passed ? 'PASS' : 'FAIL'} ${label}`);
  if (passed) ok++;
}
console.log(`RESULT: ${ok}/${checks.length} P33 premium source contracts ${ok===checks.length ? 'PASS' : 'FAIL'} (static only, not real browser/database)`);
if (ok !== checks.length) process.exitCode = 1;
