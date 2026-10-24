import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');
const suite = read('tests/Feature/Inventory/ManagedOrderHttpMySqlTest.php');
const runner = read('tools/qa/p03-http-mysql-smoke.php');
const lock = read('tools/qa/p03-mysql-lock-probe.php');
const bootstrap = read('tests/bootstrap-p03.php');
const xml = read('phpunit.p03.xml');
const adminRoutes = read('routes/admin.php');
const controller = read('app/Http/Controllers/Admin/OrderController.php');
const config = read('config/storefront.php');

const checks = [
  ['P03 default feature flags remain off', config.includes("'p03_core_test_enabled' => false") && config.includes("'p03_order_writer_test_enabled' => false")],
  ['HTTP route stays inside admin authenticated group', adminRoutes.includes("Route::middleware(['auth', 'role:admin,employee'])") && adminRoutes.includes("->name('managed_items.quantity')")],
  ['Endpoint enforces role, employee scope, and item ownership', controller.includes('$this->adminOrEmployeeOnly();') && controller.includes('$this->ensureEmployeeOrderAccess($order);') && controller.includes('(int) $item->order_id !== (int) $order->id')],
  ['Quantity endpoint validates optimistic expected quantity', controller.includes("'expected_quantity' => ['required', 'integer', 'min:1', 'max:1000']")],
  ['HTTP suite always uses database rollback transactions', suite.includes('use DatabaseTransactions;') && suite.includes('DB::transactionLevel()')],
  ['HTTP suite checks isolated test connection', suite.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['HTTP suite only creates synthetic actors and fixtures', suite.includes('P03SyntheticFixtures::create') && suite.includes('@example.test')],
  ['HTTP suite tests unauthorized guest and inactive admin', suite.includes('test_guest_cannot_patch_managed_order') && suite.includes('test_inactive_admin_cannot_patch_managed_order')],
  ['HTTP suite tests assigned and unassigned employee access', suite.includes('test_assigned_employee_can_patch_order') && suite.includes('test_unassigned_employee_cannot_patch_order')],
  ['HTTP suite tests stale forms, invalid quantity, and cross-order identity', ['test_invalid_quantity_is_rejected', 'test_stale_expected_quantity', 'test_cross_order_item'].every(s => suite.includes(s))],
  ['HTTP suite tests server-authoritative prices', suite.includes('test_patch_ignores_client_supplied_prices')],
  ['HTTP suite tests flag-off 404 and bulk refusal', suite.includes('test_route_is_not_available_when_writer_gate_is_off') && suite.includes('test_negotiated_bulk_order_quantity_is_rejected')],
  ['HTTP suite checks cancellation and dispatched-order rejection', suite.includes('test_authorized_cancel_http_request_releases_stock_once') && suite.includes('test_shipped_managed_order_cannot_cancel_via_http')],
  ['HTTP launcher requires explicit rollback-only consent', runner.includes("getenv('P03_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY'")],
  ['HTTP launcher has fixed isolated DB identity and fake APP_KEY', ['p01_campaign_test', 'p01_tester', "'APP_KEY' => 'base64:' . base64_encode(random_bytes(32))"].every(s => runner.includes(s))],
  ['HTTP launcher executes ONLY named test class', runner.includes('--filter=ManagedOrderHttpMySqlTest') && runner.includes('phpunit.p03.xml')],
  ['Prebootstrap rejects nonempty commerce database', bootstrap.includes("['products', 'orders', 'order_items', 'campaigns', 'customers']")],
  ['HTTP test config keeps CSRF middleware intact', !runner.includes('withoutMiddleware') && !suite.includes('withoutMiddleware') && xml.includes('bootstrap="tests/bootstrap-p03.php"')],
  ['Lock probe requires separate explicit authorization', lock.includes("getenv('P03_TEST_LOCK_PROBE_APPROVED') !== 'YES_READ_ONLY_LOCK_PROBE'")],
  ['Lock probe verifies both restricted connections and P02 schema', lock.includes('connectFromEnvironment()') && lock.includes("$bStatus['status'] !== 'ALREADY_APPLIED'")],
  ['Lock probe checks InnoDB row lock timeout using two connections', lock.includes('FOR UPDATE') && lock.includes('innodb_lock_wait_timeout = 1') && lock.includes('$dbCode !== 1205')],
  ['Lock probe uses only metadata and rolls back', !lock.includes('INSERT INTO') && !lock.includes('DELETE FROM') && !lock.includes('UPDATE migrations') && lock.includes('rollBack()')],
  ['No migration/seeding/reset command in the new runners', ![runner, lock].some(src => /migrate:fresh|db:wipe|\bdb:seed\b|artisan migrate|TRUNCATE TABLE|DROP TABLE/i.test(src))],
];
let passed = 0;
for (const [name, ok] of checks) {
  if (ok) {
    console.log('PASS ' + name);
    passed++;
  } else {
    console.error('FAIL ' + name);
  }
}
console.log(`Result: ${passed}/${checks.length} P03 HTTP & lock source checks ${passed === checks.length ? 'PASS' : 'FAIL'} (no DB access).`);
process.exitCode = passed === checks.length ? 0 : 1;
