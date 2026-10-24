import fs from 'node:fs';
import path from 'node:path';

// Dependency-free source contract only. Does not claim MySQL/HTTP execution.
const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const service = read('app/Services/Inventory/ManagedOrderLifecycle.php');
const controller = read('app/Http/Controllers/Admin/OrderController.php');
const smoke = read('tools/qa/p03-mysql-lifecycle-smoke.php');
const storefront = read('config/storefront.php');
const preflight = read('tests/bootstrap-p03.php');

const checks = [
  ['Both P03 flags remain off by default', /'p03_core_test_enabled'\s*=>\s*false/.test(storefront) && /'p03_order_writer_test_enabled'\s*=>\s*false/.test(storefront)],
  ['Lifecycle requires enabled writer and core', /ManagedOrderWriter::enabled\(\)/.test(service) && /p03_core_test_enabled/.test(service)],
  ['Lifecycle requires isolated test schema', service.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['Status change locks persisted order first', /whereKey\(\$orderId\)->lockForUpdate\(\)->firstOrFail\(\)/.test(service)],
  ['Status and stock release within one transaction', /DB::transaction\(function\s*\(\) use \(\$orderId, \$targetStatus, \$persist\)/.test(service) && /releaseCancelledOrder\(\$orderId/.test(service)],
  ['Rejects managed order reactivation', service.includes('cannot be reactivated without a new reviewed allocation')],
  ['Rejects shipped and courier-pending prior states', service.includes("'complete_invoice', 'cancelled', 'canceled'") && !service.includes("'courier_pending', 'cancelled'" )],
  ['Checks consignment and delivery state', service.includes('InventoryPolicy::assertCanRelease') && service.includes('pathao_consignment_id')],
  ['Rejects released/missing allocation on new cancellation', service.includes('inventory_allocated_quantity') && service.includes('no outstanding allocated stock')],
  ['Full edit guard checks transaction and managed inventory version', /assertCanFullEdit/.test(service) && /DB::transactionLevel\(\)/.test(service) && /'items' => 'Managed order item editing/.test(service)],
  ['Managed deletion and restore guarded', /assertCanDeleteOrRestore/.test(service) && /Managed stock-linked orders cannot be deleted or restored/.test(service)],
  ['Single status writer uses lifecycle only when P03 enabled', /if \(ManagedOrderWriter::enabled\(\)\) \{[\s\S]*?ManagedOrderLifecycle::class\)->transitionStatus/.test(controller)],
  ['Bulk status writer uses wrapping transaction', /return DB::transaction\(function \(\) use \(\$orders, \$status, \$extra\)/.test(controller)],
  ['Admin full edit blocks before customer mutation', /if \(ManagedOrderWriter::enabled\(\)\) \{[\s\S]*?assertCanFullEdit\(\(int\) \$order->id\);[\s\S]*?\$customer = \$customerIdentityService->resolveOrCreate/.test(controller)],
  ['Single and bulk deletion guarded', (controller.match(/assertCanDeleteOrRestore/g) ?? []).length >= 6],
  ['Test runner requires explicit rollback-only authorization', smoke.includes('P03_TEST_WRITE_APPROVED') && smoke.includes('YES_ROLLBACK_ONLY')],
  ['Preboot blocks real commerce data', /\['products', 'orders', 'order_items', 'campaigns', 'customers'\]/.test(preflight)],
  ['Runner requires restricted test identity', smoke.includes("'p01_campaign_test'") && smoke.includes("'p01_tester'")],
  ['Runner wraps synthetic fixtures in rollbacks', smoke.includes('$db->beginTransaction()') && smoke.includes('$db->rollBack()')],
  ['Lifecycle scenarios include edit and batch rollback', smoke.includes('Bulk transaction rolls back all cancellations') && smoke.includes('Full edit rejects managed lines')],
  ['Runner does not run migration or reset commands', !/artisan\s+(migrate|db:seed)|migrate:fresh|db:wipe/.test(smoke)],
];

let failed = 0;
for (const [name, okay] of checks) {
  console.log(`${okay ? 'PASS' : 'FAIL'} ${name}`);
  if (!okay) failed++;
}
console.log(`Result: ${checks.length - failed}/${checks.length} P03 lifecycle source checks ${failed ? 'PASSED' : 'PASS'} (no MySQL execution).`);
process.exitCode = failed ? 1 : 0;
