import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const read = (relativePath) => readFileSync(path.join(root, relativePath), 'utf8');
const writer = read('app/Services/Inventory/ManagedOrderWriter.php');
const mapping = read('app/Services/Inventory/DefaultVariantMapping.php');
const config = read('config/storefront.php');
const controllers = [
  ['Campaign', 'app/Http/Controllers/Admin/CampaignOrderController.php', 'products'],
  ['Manual', 'app/Http/Controllers/Admin/OrderController.php', 'items'],
  ['Bulk', 'app/Http/Controllers/Admin/BulkOrderEntryController.php', 'bulk_orders'],
];
let passed = 0;
function check(name, callback) {
  try {
    callback();
    console.log(`PASS ${name}`);
    passed++;
  } catch (error) {
    console.error(`FAIL ${name}: ${error.message}`);
    process.exitCode = 1;
  }
}
check('P03 shared core remains off by default', () => assert.match(config, /'p03_core_test_enabled'\s*=>\s*false/));
check('P03 order writer integration remains off by default', () => assert.match(config, /'p03_order_writer_test_enabled'\s*=>\s*false/));
check('Writer requires independent core gate', () => assert.match(writer, /config\('storefront\.p03_core_test_enabled', false\)/));
check('Writer requires actual isolated test connection', () => assert.match(writer, /P02MigrationSafety::assertIsolatedTestDatabase\(\)/));
check('Writer requires enclosing transaction', () => assert.match(writer, /DB::transactionLevel\(\)\s*<\s*1/));
check('Writer refuses unknown order sources', () => assert.match(writer, /admin_manual.*employee_manual.*admin_bulk.*employee_bulk/s));
check('Writer maps only persisted order items', () => assert.match(writer, /DB::table\('order_items'\).*where\('order_id', \$orderId\)/s));
check('Writer delegates to shared allocator', () => assert.match(writer, /app\(InventoryAllocator::class\)->allocateOrder\(/));
check('Bridge handles domain rejections atomically', () => assert.match(writer, /catch \(DomainException\|InvalidArgumentException \$exception\)/));
check('Mapping rejects duplicate default variants', () => assert.match(mapping, /Multiple default variants/));
check('Mapping requires active, nondeleted, default signature', () => assert.match(mapping, /'default'.*is_default.*is_active.*deleted_at/s));
for (const [label, file, field] of controllers) {
  const content = read(file);
  check(`${label} writer checks isolation before order allocation`, () => {
    const preflight = content.indexOf('ManagedOrderWriter::class)->assertTransactionReady()');
    const allocation = content.indexOf(`ManagedOrderWriter::class)->allocateCreatedOrder($order->id, '${field}')`);
    assert(preflight >= 0 && allocation > preflight);
    assert(content.includes('DB::transaction('));
  });
  check(`${label} writer has one allocation hook`, () =>
    assert.equal(content.split(`ManagedOrderWriter::class)->allocateCreatedOrder($order->id, '${field}')`).length - 1, 1));
}
check('No new public managed-checkout route registered', () => {
  for (const file of ['routes/web.php', 'routes/admin.php', 'routes/api.php']) {
    const content = read(file);
    assert(!content.includes('ManagedOrderWriter'));
  }
});
console.log(`Result: ${passed} P03 writer-source checks passed. Static inspection only; no Laravel/MySQL tests.`);
if (process.exitCode) process.exit(process.exitCode);
