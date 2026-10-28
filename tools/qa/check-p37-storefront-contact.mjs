/** Scoped source regression check; offline only, no database reads or writes. */
import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const source = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const check = (label, p, patterns) => {
  const text = source(p);
  for (const pattern of patterns) {
    if (!pattern.test(text)) throw new Error(`FAIL ${label}: expected ${pattern} in ${p}`);
  }
  console.log('PASS ' + label);
};
check('Public reviews are selected and active with media', 'app/Http/Controllers/Frontend/StorefrontCatalogController.php', [/whereNull\('campaign_id'\)/, /with\('media'\)->whereIn\('id', array_unique\(\$reviewIds\)\)/]);
check('Public and preview reviews share pinned IDs and photos', 'resources/views/storefront/partials/home-reviews.blade.php', [/\$section\['items'\]/, /review_customer_image/, /\$reviews->has/]);
check('Review sections obey builder visibility and revision order', 'resources/views/storefront/pages/home.blade.php', [/@forelse\(\$revision\['sections'\] as \$section\)/, /p35-home-reviews/, /@continue\(!\$section\['is_enabled'\]\)/]);
check('Site settings update uses existing contact fields', 'app/Http/Controllers/Admin/SiteSettingController.php', [/settingsPayload/, /business_short_description/, /email:rfc/, /address/]);
check('Customer fields are encrypted', 'app/Models/StorefrontContactMessage.php', [/SoftDeletes/, /'email' => 'encrypted'/, /'message' => 'encrypted'/]);
check('Local approved forward migration only', 'database/migrations/2026_10_27_100001_create_storefront_contact_messages.php', [/P02MigrationSafety::assertApprovedP02Up\(\)/, /Schema::create\('storefront_contact_messages'/, /throw new RuntimeException\('Contact messages rollback/]);
check('Contact form validates and uses honeypot', 'app/Http/Controllers/Frontend/StorefrontContactController.php', [/\$request->validate\(/, /company_website/, /StorefrontContactMessage::query\(\)->create/]);
check('Contact post is throttled', 'routes/storefront.php', [/Route::post\('\/contact'/, /throttle:5,1/]);
check('Admin inbox CRUD is role gated', 'routes/admin.php', [/prefix\('storefront-messages'\)/, /middleware\('role:admin'\)/, /StorefrontMessageController::class/]);
check('Admin roles enforced by controller', 'app/Http/Controllers/Admin/StorefrontMessageController.php', [/adminOnly\(\)/, /abort_unless/, /\$message->delete\(\)/]);
check('Contact details derive from active database configuration', 'app/Support/StorefrontContactDetails.php', [/where\('status', true\)/, /rawurlencode/, /www.google.com\/maps/]);
check('Contact page stores messages and displays configured map', 'resources/views/storefront/pages/contact.blade.php', [/route\('storefront.contact.submit'\)/, /@csrf/, /\$details\['map_embed'\]/]);
check('Footer reads actual contact and social data', 'resources/views/storefront/partials/footer.blade.php', [/StorefrontContactDetails/, /socialLinks/, /\$details\['phone'\]/]);
console.log('RESULT: 13/13 P37 source contracts PASS (offline; no MySQL/browser)');
