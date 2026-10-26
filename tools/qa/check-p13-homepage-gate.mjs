/** P13 source-only reproduction/contract: misleading homepage menu vs P05 DB guard. */
import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';

const root = path.resolve(import.meta.dirname, '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
let passed = 0;
let total = 0;
function check(name, ok) {
  total++;
  if (ok) { console.log('PASS ' + name); passed++; }
  else { console.error('FAIL ' + name); process.exitCode = 1; }
}
const config = read('config/adminlte.php');
const provider = read('app/Providers/AppServiceProvider.php');
const controller = read('app/Http/Controllers/Admin/HomepageBuilderController.php');
const service = read('app/Services/Storefront/HomepageBuilder.php');
const safety = read('app/Support/P02MigrationSafety.php');
const browser = read('tools/qa/p13-isolated-admin-browser.ps1');
const readiness = read('tools/qa/p13-browser-readiness.php');
const unittest = read('tests/Unit/P13HomepageMenuGateTest.php');
const sync = read('tools/qa/p13-sync-source-to-test.ps1');
const routeFiles = ['admin.php', 'api.php', 'command.php', 'console.php', 'storefront.php', 'user.php', 'web.php'];
const approvedRouteHashes = {
  'admin.php': '2f68278de6efe46ec66225a057209b4e155505bd524471b355d3d6b31b398254', // P24: reviewed active-admin-only preview shortcut
  'api.php': '3144a774cccb127f0881e7d34ce43e963356dad03bf29bc59885cee7a548fdbf',
  'command.php': '2f66d33c8fb0436ee246c822e7799d13a9cca4789ffdeb0469ef81255a0fd02c',
  'console.php': 'd0ef5e225aed6e5a558dcf7c6a2cb76f109534d2e70b6f3d98c960ee557c12b8',
  'storefront.php': 'c5a07336f86270178be73ec7c551832e89cf06805923233dc35b3412b8c9a6a2',
  'user.php': 'a0897fdf9df2e0e17a7be4833ab2b33e3489a4640dd3b62b60a85b0ea44102b5',
  'web.php': 'c3819a296e7965d5a4bf3ebdd887264759dbacc121c1d15c635fe536974163ea',
};
check('Outdated P05 TEST sidebar caption removed', !config.includes('STOREFRONT (P05 TEST)'));
check('Sidebar advertises isolated QA only', config.includes('HOMEPAGE MANAGEMENT'));
check('Both sidebar header and link require QA Gate', (config.match(/'can'\s*=>\s*'isolated-homepage-builder'/g) ?? []).length === 2);
check('Real homepage URL remains unchanged', config.includes("'url'    => 'admin/storefront/home'"));
check('Gate requires active admin', provider.includes("$user->role !== 'admin'") && provider.includes('$user->is_active'));
check('Gate accepts local/test environments only', provider.includes("app()->environment(['local', 'testing'])"));
check('Gate checks explicit test-schema identity', provider.includes("'p01_campaign_test'") && provider.includes("'p01_tester'") && provider.includes("'127.0.0.1'"));
check('Gate rejects unsafe alternate connections', provider.includes("empty($db['url'])") && provider.includes("empty($db['read'])") && provider.includes("empty($db['write'])"));
check('P14 local option remains a separate explicit branch', provider.includes('LocalHomepageAccess::configuredForBuilder()'));
check('Controller independently guards direct URL', controller.includes('private function adminOnly(') && controller.includes('$builder->assertTestSchema();'));
check('Builder independently enforces isolated DB', service.includes('P02MigrationSafety::assertIsolatedTestDatabase()'));
check('Builder re-verifies actual approved local database', service.includes('LocalHomepageAccess::assertActualBuilderDatabase()'));
check('Underlying guard confirms real database identity', safety.includes('SELECT DATABASE() AS schema_name, CURRENT_USER() AS account'));
check('Staged route remains admin scoped', read('routes/admin.php').includes("Route::middleware('role:admin')->prefix('storefront/home')"));
check('Guarded browser preview restricted to test copy', browser.includes("'website-p12-test'") && browser.includes('p02-mysql-preflight.php'));
check('Browser preview uses isolated nonproduction DB only', browser.includes("'p01_campaign_test'") && browser.includes("'p01_tester'"));
check('Browser preview preserves scoped DB environment', browser.includes('artisan serve --no-reload'));
check('Browser preview has distinct session cookie', browser.includes('p13_isolated_qa_session'));
check('Browser preview does not use an original APP_KEY', browser.includes('P12 synthetic test key') && browser.includes('APP_KEY'));
check('QA-only catalog is available behind restricted DB identity', browser.includes("'P07_CATALOG_TEST_ENABLED' = 'true'"));
check('Browser preview leaves checkout test flags OFF', browser.includes('P08_CART_TEST_ENABLED') && browser.includes('P08_CHECKOUT_TEST_ENABLED'));
check('Read-only test-admin presence check', readiness.includes('P02TestSchemaGate::connectFromEnvironment()') && readiness.includes('COUNT(*)') && !/\b(?:INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE)\s+/i.test(readiness.replaceAll(/\/\*[\s\S]*?\*\/|\/\/[^\n]*/g, '')));
check('Unit regression covers original DB and inactive/employee access', unittest.includes('ecommerce_landing_db') && unittest.includes("'employee'") && unittest.includes("'admin', false"));
check('All seven historical route files exist in the actual website',
  routeFiles.every((file) => fs.statSync(path.join(root, 'routes', file), { throwIfNoEntry: false })?.isFile()));
check('Reviewed seven route files retain approved SHA256 (storefront extension P19)', routeFiles.every((file) => {
  const target = path.join(root, 'routes', file);
  return fs.existsSync(target) && createHash('sha256').update(fs.readFileSync(target)).digest('hex') === approvedRouteHashes[file];
}));
check('Safe copy requires P12 synthetic environment and a restricted test directory',
  sync.includes("'website-p12-test'") && sync.includes("'DB_DATABASE' = ':memory:'")
  && sync.includes("'DB_CONNECTION' = 'sqlite'") && sync.includes("'APP_ENV' = 'testing'"));
check('Safe copy never transfers secrets, data files, vendor or routes',
  sync.includes('Exact allowlist') && sync.includes('No files copied')
  && !/Copy-Item[^\n]*\.env/i.test(sync) && !/Copy-Item[^\n]*\broutes\b/i.test(sync));
check('Safe copy verifies source fingerprint and pins route/lock matches',
  sync.includes('p11-source-manifest.mjs') && sync.includes('check-p13-homepage-gate.mjs')
  && sync.includes('Get-FileHash') && sync.includes('composer.lock'));
check('Safe copy provides backup and digest verification',
  sync.includes('.p13-sync-backup-') && sync.includes('copied file hash mismatch'));
console.log(`RESULT: ${passed}/${total} P13 Homepage QA contracts ${process.exitCode ? 'FAIL' : 'PASS'} (offline; no DB).`);
