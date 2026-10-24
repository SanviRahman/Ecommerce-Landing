import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const at = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const testsRoot = path.join(root, 'tests');
const files = [];
function visit(folder) {
  for (const entry of fs.readdirSync(folder, { withFileTypes: true })) {
    const filepath = path.join(folder, entry.name);
    if (entry.isDirectory()) visit(filepath);
    else if (entry.isFile() && entry.name.endsWith('.php')) files.push(filepath);
  }
}
visit(testsRoot);
const namespaceFailures = [];
for (const filepath of files) {
  const source = fs.readFileSync(filepath, 'utf8');
  const ns = source.match(/^namespace\s+([^;]+);/m)?.[1];
  if (!ns) continue; // standalone bootstrap scripts
  const relative = path.relative(testsRoot, filepath);
  const folders = path.dirname(relative).split(path.sep).filter((n) => n !== '.');
  const expected = ['Tests', ...folders].join('\\');
  if (ns !== expected) namespaceFailures.push(`${relative}: ${ns} != ${expected}`);
}
if (namespaceFailures.length) console.error('PSR-4 mismatches:', ...namespaceFailures);

const campaign = at('tests/Feature/CampaignMembershipTest.php');
const maintenance = at('tests/Feature/MaintenanceRoutesTest.php');
const xml = at('phpunit.p01.xml');
const runner = at('tools/qa/p01-http-mysql-smoke.php');
const bootstrap = at('tests/bootstrap-p03.php');
const storefront = at('config/storefront.php');
const checks = [
  ['All namespaced test files align with Tests\\ PSR-4 path', namespaceFailures.length === 0],
  ['Campaign characterization class has matching namespace', campaign.includes('namespace Tests\\Feature;') && campaign.includes('class CampaignMembershipTest')],
  ['Maintenance class has matching namespace', maintenance.includes('namespace Tests\\Feature;') && maintenance.includes('class MaintenanceRoutesTest')],
  ['P01 suite selects existing campaign characterization file', xml.includes('<file>tests/Feature/CampaignMembershipTest.php</file>')],
  ['P01 suite selects existing maintenance security file', xml.includes('<file>tests/Feature/MaintenanceRoutesTest.php</file>')],
  ['P01 bootstrap reuses isolated P02/P03 safety preflight', xml.includes('bootstrap="tests/bootstrap-p03.php"') && bootstrap.includes('P02TestSchemaGate::evaluate')],
  ['P01 XML fixes MySQL test schema, account and local host', ['p01_campaign_test', 'p01_tester', '127.0.0.1', '3306'].every((s) => xml.includes(s))],
  ['P01 runner requires separate rollback-only approval', runner.includes("getenv('P01_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY'")],
  ['P01 runner reads test password from process environment', runner.includes("getenv('P02_TEST_DB_PASSWORD')") && !runner.includes('file_get_contents(.env')],
  ['P01 runner bootstraps fake key and fixed database', runner.includes('base64_encode(random_bytes(32))') && runner.includes("'DB_DATABASE' => 'p01_campaign_test'")],
  ['P01 runner executes only the named P01 suite', runner.includes("'--testsuite=P01'") && runner.includes("'--configuration=' . $root . '/phpunit.p01.xml'")],
  ['P01 runner refuses zero or missing tests', runner.includes('$count !== 6') && runner.includes("'--log-junit=' . $reportFile")],
  ['P01 runner has no migrations, seeds or dangerous resets', !/migrate:fresh|db:wipe|artisan migrate|db:seed|TRUNCATE TABLE|DROP TABLE/i.test(runner)],
  ['P03 production switches remain disabled', storefront.includes("'p03_core_test_enabled' => false") && storefront.includes("'p03_order_writer_test_enabled' => false")],
];
let pass = 0;
for (const [label, good] of checks) {
  console.log(`${good ? 'PASS' : 'FAIL'} ${label}`);
  if (good) pass++;
}
console.log(`Result: ${pass}/${checks.length} P01 PSR-4 and isolated test-gate checks ${pass === checks.length ? 'PASS' : 'FAIL'} (no DB access).`);
process.exitCode = pass === checks.length ? 0 : 1;
