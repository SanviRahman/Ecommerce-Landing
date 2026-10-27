import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const checks = [
  ['Independent FAQ table', read('database/migrations/2026_10_27_110001_create_storefront_faqs.php').includes("Schema::create('storefront_faqs'")],
  ['FAQ migration identity guarded', read('database/migrations/2026_10_27_110001_create_storefront_faqs.php').includes('P02MigrationSafety::assertApprovedP02Up()')],
  ['Separate public FAQ read', read('app/Http/Controllers/Frontend/StorefrontCatalogController.php').includes('StorefrontFaq::query()->published()')],
  ['Campaign FAQ remains', fs.existsSync(path.join(root, 'app/Models/Faq.php')) && fs.existsSync(path.join(root, 'app/Http/Controllers/Admin/FaqController.php'))],
  ['FAQ Admin-only routes', read('routes/admin.php').includes("prefix('storefront-faqs')->as('storefront-faqs.')->middleware('role:admin')")],
  ['FAQ create/update/delete protected', ['@csrf','@method(\'PUT\')','@method(\'DELETE\')'].every(x => read('resources/views/admin/storefront-faqs/form.blade.php').includes(x) || read('resources/views/admin/storefront-faqs/index.blade.php').includes(x))],
  ['Separate storefront media collections', ['storefront_logo','storefront_white_logo','storefront_favicon'].every(x => read('app/Models/SiteSetting.php').includes(x))],
  ['Storefront public uses its media', read('app/Support/StorefrontBranding.php').includes("getFirstMediaUrl('storefront_logo')") && read('app/Support/StorefrontBranding.php').includes("getFirstMediaUrl('storefront_favicon')")],
  ['Old campaign collection retained', ['site_logo','site_white_logo','site_favicon'].every(x => read('app/Models/SiteSetting.php').includes(x))],
  ['Contact links from safe service', read('resources/views/storefront/partials/contact-widget.blade.php').includes('StorefrontContactLinks::class')],
  ['Contact accessible keyboard JS', read('public/frontend/js/storefront-p38.js').includes("event.key === 'Escape'")],
  ['Contact CSS keeps cart', !read('public/frontend/css/storefront-p38.css').includes('.p19-floating-cart{display:none')],
  ['Archived-only deletion gate', read('app/Services/Storefront/HomepageBuilder.php').includes("$revision->status !== 'archived'")],
  ['Archive hide is non-destructive', read('app/Services/Storefront/HomepageBuilder.php').includes("['deleted_at' => now()")],
  ['Archived deletion versioned and audited', read('app/Services/Storefront/HomepageBuilder.php').includes("'revision_hide_archived'")],
  ['Admin deletion CSRF method', read('resources/views/admin/storefront/homepage.blade.php').includes("@csrf @method('DELETE')")],
  ['Separate revision migration guarded', read('database/migrations/2026_10_27_110002_add_archived_revision_visibility.php').includes('P02MigrationSafety::assertApprovedP02Up()')],
];
for (const [name, valid] of checks) {
  process.stdout.write(`${valid ? 'PASS' : 'FAIL'} ${name}\n`);
}
const failed = checks.filter(([, ok]) => !ok).length;
console.log(`RESULT: ${checks.length - failed}/${checks.length} P38 source contracts PASS (no DB/browser)`);
if (failed) process.exitCode = 1;
