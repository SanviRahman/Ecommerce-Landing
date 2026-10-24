import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const cli = read('tools/qa/p03-mysql-transaction-smoke.php');
const fixtures = read('tools/qa/P03SyntheticFixtures.php');
const bootstrap = read('tests/bootstrap-p03.php');
const test = read('tests/Feature/Inventory/ManagedOrderWriterMySqlTest.php');
const xml = read('phpunit.p03.xml');
const config = read('config/storefront.php');

const cases = [
  ['CLI requires explicit rollback-only authorization', cli.includes("P03_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY'")],
  ['Test bootstrap requires exact local MySQL identity', bootstrap.includes("'DB_DATABASE' => 'p01_campaign_test'") && bootstrap.includes("'DB_USERNAME' => 'p01_tester'")],
  ['Prebootstrap checks denied access and P02 schema', bootstrap.includes('P02TestSchemaGate::connectFromEnvironment()') && bootstrap.includes("'ALREADY_APPLIED'")],
  ['Prebootstrap rejects existing commerce records', bootstrap.includes("['products', 'orders', 'order_items', 'campaigns', 'customers']")],
  ['Fixture factory refuses missing transaction', fixtures.includes('DB::transactionLevel() < 1') && fixtures.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['Actual Laravel writer used and rolled back per case', cli.includes('allocateCreatedOrder') && cli.includes('$db->beginTransaction()') && cli.includes('$db->rollBack()')],
  ['Smoke handles stock, campaign, manual and bulk', ['Frontend order deducts', 'Manual order uses', 'Bulk order uses', 'Campaign order preserves', 'Oversell rejection'].every(s => cli.includes(s))],
  ['Tests cover tampering, replay, cancellation and checkout', ['test_wrong_price_fails', 'test_replaying_verified_allocation', 'test_explicit_pre_dispatch_release', 'test_duplicate_checkout_key'].every(s => test.includes(s))],
  ['PHPUnit suite uses guarded bootstrap and transactions', xml.includes('tests/bootstrap-p03.php') && test.includes('use DatabaseTransactions;')],
  ['Production feature switches remain OFF', config.includes("'p03_core_test_enabled' => false") && config.includes("'p03_order_writer_test_enabled' => false")],
  ['No migration/reset/seed commands in integration runner', !/migrate(?::fresh)?|db:wipe|\bdb:seed\b|TRUNCATE\s+TABLE/i.test(cli)],
];
let pass = 0;
for (const [label, ok] of cases) {
  assert.equal(ok, true, label);
  console.log('PASS ' + label);
  pass++;
}
console.log(`Result: ${pass}/${cases.length} P03 runtime-test source checks PASS (not a MySQL execution).`);
