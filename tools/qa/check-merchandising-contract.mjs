// Standalone source-level regression checks (no DB/app boot; not an integration test).
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
const root = path.resolve(import.meta.dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const files = {
  route: read('routes/admin.php'), menu: read('config/adminlte.php'),
  controller: read('app/Http/Controllers/Admin/CatalogOrderController.php'),
  view: read('resources/views/admin/categories/order.blade.php'),
  nav: read('resources/views/storefront/partials/navigation.blade.php'),
  settings: read('app/Http/Controllers/Admin/SiteSettingController.php'),
  settingsView: read('resources/views/admin/site-settings/index.blade.php'),
  categoryForm: read('resources/views/admin/categories/partials/form.blade.php'),
  migration: read('database/migrations/2026_11_03_100001_add_catalog_order_and_optional_site_name.php'),
  catalog: read('app/Services/Storefront/PublicCatalog.php'),
  shelf: read('app/Services/Storefront/CategoryShelf.php'),
  public: read('app/Http/Controllers/Frontend/StorefrontCatalogController.php'),
  publicHome: read('resources/views/storefront/pages/home.blade.php'),
  adminHome: read('resources/views/admin/storefront/homepage.blade.php'),
  homeBuilder: read('app/Services/Storefront/HomepageBuilder.php'),
};
const tests = [
 ['Admin-only GET/PATCH endpoints and one sidebar entry', () => {
   assert.match(files.route, /middleware\('role:admin'\)->prefix\('catalog-order'\)/);
   assert.match(files.route, /Route::patch\('\/categories'/);
   assert.match(files.route, /Route::patch\('\/products'/);
   assert.equal(files.menu.match(/'url'\s*=>\s*'admin\/catalog-order'/g)?.length, 1);
 }],
 ['Strict complete-permutation validation, DB row locks, and bounded category ownership', () => {
   assert.match(files.controller, /'distinct'/);
   assert.match(files.controller, /lockForUpdate\(\)/);
   assert.match(files.controller, /\$existing !== \$submitted/);
   assert.match(files.controller, /where\('category_id', \$categoryId\)/);
   assert.match(files.controller, /\$parentById\[\$id\]->parent_id/);
 }],
 ['Mobile-accessible ordering with no new dependencies or hidden GET writes', () => {
   assert.match(files.view, /data-move="up"/);
   assert.match(files.view, /data-move="down"/);
   assert.match(files.view, /@csrf @method\('PATCH'\)/);
   assert.match(files.view, /list\.insertBefore\(item\.nextElementSibling, item\)/);
 }],
 ['Logo and website text are exclusive; name is nullable end to end', () => {
   assert.match(files.nav, /@if\(\$brandLogo\)/);
   const branding = files.nav.match(/<a class="p07-brand p33-logo-brand[\s\S]*?<\/a>/)?.[0] ?? '';
   assert.match(branding, /@else\s+<span class="p33-brand-name"/);
   assert.doesNotMatch(branding, /p07-brand-logo/);
   assert.match(files.settings, /'website_name' => \['nullable', 'string'/);
   assert.match(files.settings, /'website_name' => \$name === '' \? null : \$name/);
   assert.doesNotMatch(files.settingsView, /name="website_name"[\s\S]{0,180}required>/);
   assert.match(files.migration, /->string\('website_name'\)->nullable\(\)->change\(\)/);
 }],
 ['Single product order joins catalog category views with homepage shelves', () => {
   assert.match(files.migration, /->unsignedInteger\('sort_order'\)->default\(0\)/);
   assert.match(files.catalog, /'manual' => \$query/);
   assert.match(files.catalog, /->orderBy\('products.sort_order'\)/);
   assert.match(files.public, /\$sort = \$data\['sort'\] \?\? 'manual';/);
   assert.match(files.shelf, /->whereIn\('products.category_id', \$ids\), 'manual'\)/);
   assert.match(files.adminHome, /Manage Category &amp; Product Order/);
   assert.match(files.categoryForm, /route\('admin.catalog-order.index'/);
   assert.doesNotMatch(files.categoryForm, /name="sort_order"/);
   assert.match(files.publicHome, /\$catalog->orderedCategoryIds/);
   assert.match(files.homeBuilder, /\$sort = 'manual';/);
 }],
 ['Cart, checkout, inventory, and publish routes not modified by this test feature', () => {
   const r = files.route;
   assert.match(r, /HomepageBuilderController::class, 'publish'/);
   assert.doesNotMatch(files.controller, /StorefrontCheckout|InventoryAllocator|ManagedOrderLifecycle|CartService|HomepageBuilder/);
 }],
];
let count = 0;
for (const [name, fn] of tests) {
 try { fn(); console.log('PASS ' + name); count++; }
 catch (error) { console.error('FAIL ' + name + ' — ' + error.message); }
}
console.log(`RESULT ${count}/${tests.length} independent source contracts ${count === tests.length ? 'PASS' : 'FAIL'}`);
if (count !== tests.length) process.exitCode = 1;
