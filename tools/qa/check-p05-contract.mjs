import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const service = read('app/Services/Storefront/HomepageBuilder.php');
const controller = read('app/Http/Controllers/Admin/HomepageBuilderController.php');
const routes = read('routes/admin.php');
const web = read('routes/user.php');
const config = read('config/adminlte.php');
const blade = read('resources/views/admin/storefront/homepage.blade.php');
const preview = read('resources/views/storefront/pages/home.blade.php');
const previewWrapper = read('resources/views/storefront/preview/home.blade.php');
const css = read('public/frontend/css/storefront-preview.css');
const schema = read('app/Support/HomeSectionSchema.php');
const runner = read('tools/qa/p05-mysql-homepage-smoke.php');
const tests = read('tests/Feature/Storefront/P05HomepageMySqlTest.php');
const checks = [
  ['P05 mutations require verified isolated MySQL identity', service.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['P05 requires complete existing homepage schema', service.includes('homepage_audit_events') && service.includes('storefront_revisions')],
  ['P05 does not modify original root campaign view', /Route::get\('\/'\s*,\s*\[HomeController::class,\s*'index'\]\)/.test(web)],
  ['Only five known component keys permitted', /const TYPES/.test(schema) && !schema.includes('arbitrary_html')],
  ['Draft and published revision pointers separate', service.includes('draft_revision_id') && service.includes('published_revision_id')],
  ['Draft cloning creates distinct section identities', service.includes('cloneRevision') && service.includes("unset($row['id'])")],
  ['Published revisions validated before pointer swap', service.includes('validateForPublication((int) $draft->id)')],
  ['Publication does not delete historical revisions', service.includes("'status' => 'archived'") && !service.includes('Schema::drop')],
  ['Stale saves rejected with HTTP 409 current version', service.includes('new HttpException(409') && service.includes('Current version:')],
  ['Reorder validates exact permutation', service.includes('array_unique($keys)') && service.includes('array_diff($stored, $keys)')],
  ['Up/down accessible movement supported', service.includes("['up', 'down']") && blade.includes('direction')],
  ['All mutations record audit and version changes', service.includes('homepage_audit_events') && service.includes('lock_version')],
  ['Section selection rejects inactive/deleted resources', service.includes("whereNull('deleted_at')") && service.includes("where('status', true)")],
  ['Publish revalidates media and active reviews', service.includes('eligibleMedia') && service.includes('eligibleReview')],
  ['Media selection limited to public approved collections', service.includes('PUBLIC_IMAGE_COLLECTIONS') && service.includes("where('disk', 'public')")],
  ['No arbitrary CSS or view paths stored', schema.includes('normalizeColors') && schema.includes('STYLE_KEYS') && !schema.includes('eval(')],
  ['Theme publish validates foreground contrast', schema.includes('validateThemeContrast') && service.includes('validateThemeContrast')],
  ['URL validator rejects unsafe protocols', schema.includes("'https'") && schema.includes('Invalid link URL.')],
  ['Routes protected by admin and CSRF default middleware', routes.includes("Route::middleware('role:admin')->prefix('storefront/home')") && blade.includes('@csrf')],
  ['Controller explicitly denies inactive users', controller.includes('is_active') && controller.includes('isAdmin()')],
  ['P05 no new public storefront checkout routes', !web.includes('storefront.home') && !routes.includes('checkout.storefront')],
  ['Preview delegates to reusable storefront homepage Blade', previewWrapper.includes("@include('storefront.pages.home'")],
  ['Preview restricted to authenticated admin route', routes.includes("HomepageBuilderController::class, 'preview'") && controller.includes('adminOnly($builder)')],
  ['Preview prevents public cache and search indexing', controller.includes('no-store') && controller.includes('noindex, nofollow')],
  ['Preview renders escaped text, not raw HTML', preview.includes('{{ $item[\'title\']') && !preview.includes('{!!')],
  ['Preview has separate stylesheet from campaign site', preview.includes('frontend/css/storefront-preview.css') && css.includes('.sf-section')],
  ['Responsive 4 desktop and 2 mobile tile contract', css.includes('repeat(4,minmax(0,1fr))') && css.includes('repeat(2,minmax(0,1fr))')],
  ['Cart and Buy preview controls are inert until P08', preview.includes('sf-inert') && !preview.includes('checkout.store')],
  ['P05 runner requires separate explicit authorization', runner.includes("getenv('P05_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY'")],
  ['P05 runner preboots on isolated schema and empty tables', runner.includes('bootstrap-p03.php') && runner.includes('storefront_pages')],
  ['MySQL feature cases use rollback transactions', tests.includes('use DatabaseTransactions;') && tests.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['Test runner has no migration/reset/seed commands', !/migrate:fresh|migrate:reset|db:wipe|db:seed|Artisan::call\(\s*['"]migrate/i.test(runner)],
  ['Existing P03 default feature switches stay disabled', read('config/storefront.php').includes("'p03_order_writer_test_enabled' => false")],
];
let good = 0;
for (const [name, ok] of checks) {
  if (!ok) { console.error('FAIL ' + name); process.exitCode = 1; }
  else { console.log('PASS ' + name); good++; }
}
console.log(`Result: ${good}/${checks.length} P05 source contract checks ${process.exitCode ? 'FAILED' : 'PASS'} (no database).`);
