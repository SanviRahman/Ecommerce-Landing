/** P14 source-only guard review: no migrations, no SQL and no .env reads. */
import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const assert = (description, okay) => {
  if (!okay) { console.error('FAIL ' + description); process.exitCode = 1; }
  else { console.log('PASS ' + description); }
  return okay;
};
const local = read('app/Support/LocalHomepageAccess.php');
const migrationSafety = read('app/Support/P02MigrationSafety.php');
const controller = read('app/Http/Controllers/Admin/HomepageBuilderController.php');
const service = read('app/Services/Storefront/HomepageBuilder.php');
const provider = read('app/Providers/AppServiceProvider.php');
const menu = read('config/adminlte.php');
const flags = read('config/storefront.php');
const route = read('routes/admin.php');
let passed=0, total=0;
function check(description, okay) { total++; if (assert(description,okay)) passed++; }
check('Local DB is pinned to original named local schema', local.includes("DATABASE = 'ecommerce_landing_db'"));
check('Permission off without explicit local-only consent', local.includes('return $explicitApproval') && flags.includes("env('P14_LOCAL_HOMEPAGE_BUILDER_ENABLED', false)"));
check('Separate migration consent is required', local.includes('MIGRATION_CONSENT') && flags.includes("env('P14_LOCAL_P02_MIGRATION_APPROVED', '')"));
check('Local-only and APP_DEBUG guard', local.includes("$environment === 'local'") && local.includes('$debug === true'));
check('Pinned MySQL port and host', local.includes("'127.0.0.1'") && local.includes('=== 3306'));
check('Pinned MySQL driver and default connection', local.includes("$defaultConnection === 'mysql'") && local.includes("=== 'mysql'"));
check('DB URL/socket/split routing denied', ['url','unix_socket','read','write'].every(k => local.includes(`empty($db['${k}'])`)));
check('Actual schema and account reverified before CRUD', local.includes('SELECT DATABASE() AS schema_name, CURRENT_USER() AS account') && local.includes('assertActualBuilderDatabase()'));
check('Migration guard only operates in CLI', local.includes('app()->runningInConsole()'));
check('Existing restricted P02 validation remains', migrationSafety.includes('public static function assertIsolatedTestDatabase()') && migrationSafety.includes("'p01_campaign_test'"));
check('Separate forward-only P02 entry point', migrationSafety.includes('public static function assertApprovedP02Up()') && migrationSafety.includes('LocalHomepageAccess::assertActualMigrationDatabase()'));
check('Homepage service retains isolated and explicit local runtime gates', service.includes('P02MigrationSafety::assertIsolatedTestDatabase()') && service.includes('LocalHomepageAccess::assertActualBuilderDatabase()'));
check('AdminLTE visibility is the OR of isolated and local preflight', provider.includes('return $isolatedTest || LocalHomepageAccess::configuredForBuilder();'));
check('Both sidebar header and link enforce admin Gate', (menu.match(/'can'\s*=>\s*'isolated-homepage-builder'/g) ?? []).length === 2);
check('No legacy P05 TEST label', !menu.includes('STOREFRONT (P05 TEST)') && menu.includes('HOMEPAGE MANAGEMENT'));
check('Controller always checks active admin and schema', controller.includes('$builder->assertTestSchema();') && controller.includes('isAdmin()') && controller.includes('is_active'));
check('Pending schema gives explicit error not route 404', controller.includes("abort(503, 'Homepage Builder requires the reviewed P02 migrations"));
check('Routes remain admin role scoped', route.includes("Route::middleware('role:admin')->prefix('storefront/home')"));
const preflight = read('tools/qa/p14-local-migration-preflight.php');
check('Local migration readiness is read-only, uses real identity and 11-file history',
  preflight.includes('LocalHomepageAccess::assertActualMigrationDatabase()')
  && preflight.includes("DB::table('migrations')")
  && preflight.includes('count($names) !== 11')
  && !/\b(?:INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE)\b\s+(?:INTO|FROM|TABLE|DATABASE)/i.test(preflight.replaceAll(/\/\*[\s\S]*?\*\/|\/\/[^\n]*/g, '')));

check('Admin page presents correct local-vs-QA scope and frontend preview', read('resources/views/admin/storefront/homepage.blade.php').includes('Local development Homepage Builder') && read('resources/views/admin/storefront/homepage.blade.php').includes('Preview homepage'));
check('Legacy payment/cart flags remain OFF', flags.includes("'p03_core_test_enabled' => false") && flags.includes("'p03_order_writer_test_enabled' => false") && flags.includes("env('P08_CHECKOUT_TEST_ENABLED', false)"));
const migrations = fs.readdirSync(path.join(root,'database/migrations'))
  .filter(n => /^2026_10_24_2100\d\d_p02_.*\.php$/.test(n)).sort();
check('Exactly eleven P02 migrations remain', migrations.length === 11);
for (const file of migrations) {
  const source = read('database/migrations/' + file);
  const [up, down] = source.split('public function down(): void');
  check(file + ': up uses guarded local/test approval, down remains isolated ONLY',
    up.includes('P02MigrationSafety::assertApprovedP02Up();')
    && !up.includes('assertIsolatedTestDatabase();')
    && down?.includes('P02MigrationSafety::assertIsolatedTestDatabase();')
    && !down?.includes('assertApprovedP02Up();'));
}
console.log(`RESULT: ${passed}/${total} P14 local Homepage source contracts ${process.exitCode ? 'FAIL' : 'PASS'} (no DB).`);
