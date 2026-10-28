/** P40 offline source contract checks. These do not replace Laravel/MySQL/browser tests. */
import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
const root = resolve(import.meta.dirname, '../..');
const read = (path) => readFileSync(resolve(root, path), 'utf8');
let checked = 0;
function check(condition, message) {
  assert.ok(condition, message);
  checked++;
}
const categories = read('app/Models/Category.php');
const categoryView = read('resources/views/storefront/pages/shop.blade.php');
const categoryNav = read('resources/views/storefront/partials/navigation.blade.php');
check(categories.includes("'parent_id'") && categories.includes('function children()'), 'category hierarchy');
check(categoryView.includes('p40-subcategory-section') && categoryView.includes('$selectedParent->children'), 'parent subcategory discovery');
check(categoryNav.includes('p19-parent-category') && categoryNav.includes('foreach($parent->children as $child)'), 'navigation nested categories');

const routes = read('routes/admin.php');
const optionManager = read('app/Services/Catalog/VariantCatalogManager.php');
const optionUi = read('resources/views/admin/products/options.blade.php');
const productForm = read('resources/views/admin/products/partials/form.blade.php');
for (const action of ['options.values.store', 'options.values.update', 'options.values.destroy']) {
  check(routes.includes(`name('${action}')`), `admin CRUD route: ${action}`);
}
check(optionManager.includes('function updateOptionValue(') && optionManager.includes('function deleteOptionValue('), 'option lifecycle methods');
check(optionManager.includes("where('product_option_value_id', $row->id)->exists()"), 'referenced values protected from deletion');
check(optionManager.includes('Active variants use this value'), 'active variant deactivation check');
check(optionUi.includes("@method('PATCH')") && optionUi.includes("@method('DELETE')") && optionUi.includes('@csrf'), 'option edit/delete CSRF');
check(productForm.includes("'color' => 'Color', 'size' => 'Size'") && productForm.includes('initial_variation'), 'new product selectors');

const migration = 'database/migrations/2026_10_27_120001_add_storefront_footer_social_to_site_settings.php';
check(existsSync(resolve(root, migration)), 'site social migration exists');
const settings = read('app/Http/Controllers/Admin/SiteSettingController.php');
const branding = read('app/Support/StorefrontBranding.php');
const footer = read('resources/views/storefront/partials/footer.blade.php');
check(settings.includes('footer_youtube_url') && settings.includes('footer_social_managed'), 'social fields persisted');
check(settings.includes("'url:https'"), 'social links require HTTPS');
check(branding.includes('footer_social_managed') && branding.includes("'https://wa.me/'") && branding.includes("'tel:'"), 'footer links built from site settings');
check(footer.includes('$socialLinks = $branding->socialLinks()'), 'footer renders managed links');

const topJs = read('public/frontend/js/back-to-top.js');
const topCss = read('public/frontend/css/back-to-top.css');
const admin = read('config/adminlte.php');
for (const layout of ['resources/views/frontend/layouts/master.blade.php', 'resources/views/storefront/layouts/catalog.blade.php', 'resources/views/layouts/app.blade.php']) {
  check(read(layout).includes('back-to-top.js') && read(layout).includes('back-to-top.css'), `shared scroll assets in ${layout}`);
}
check(existsSync(resolve(root, 'public/vendor/adminlte/dist/img/no-image.png')) && existsSync(resolve(root, 'public/frontend/images/placeholder.png')), 'broken-image fallbacks now have real assets');
check(admin.includes("'StorefrontBackToTop'") && admin.includes("'active' => true"), 'AdminLTE loads scroll assets');
check(topJs.includes('prefers-reduced-motion') && topJs.includes('window.scrollTo') && topJs.includes('scrollY > 320'), 'scroll motion respects accessibility');
check(topCss.includes('.sf-back-to-top') && topCss.includes('@keyframes'), 'scroll visual animation');
console.log(`PASS P40: ${checked} offline source contracts (not DB/browser integration tests)`);
