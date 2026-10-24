import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const editor = read('app/Services/Inventory/ManagedOrderQuantityEditor.php');
const controller = read('app/Http/Controllers/Admin/OrderController.php');
const routes = read('routes/admin.php');
const blade = read('resources/views/admin/orders/edit.blade.php');
const smoke = read('tools/qa/p03-mysql-quantity-smoke.php');
const config = read('config/storefront.php');
const safety = read('app/Support/P02MigrationSafety.php');

const checks = [
  ['Core and writer are disabled by default', config.includes("'p03_core_test_enabled' => false") && config.includes("'p03_order_writer_test_enabled' => false")],
  ['Independent writer gate in quantity service', editor.includes('if (! ManagedOrderWriter::enabled()') && editor.includes('assertTransactionReady()')],
  ['Test connection gate checks server account', safety.includes('p01_campaign_test') && safety.includes('p01_tester') && editor.includes('assertTransactionReady()')],
  ['Order is locked before item/product/variant', editor.indexOf("DB::table('orders')") < editor.indexOf("DB::table('order_items')->where('id'") && editor.indexOf("DB::table('order_items')->where('id'") < editor.indexOf("DB::table('products')") && editor.indexOf("DB::table('products')") < editor.indexOf("DB::table('product_variants')->where('id'" )],
  ['Pre-dispatch and courier/collected guards', ['complete_invoice', 'shipped_at', 'delivered_at', 'steadfast_consignment_id', 'pathao_consignment_id', 'collected'].every(x => editor.includes(x))],
  ['Bulk and external order revisions refused', editor.includes("['frontend', 'admin_manual', 'employee_manual']")],
  ['Cross-order item and stale form rejected', editor.includes("->where('order_id', $orderId)") && editor.includes('$expectedQuantity') && editor.includes('changed since this form was opened')],
  ['Variant linkage and stock mirror validated', editor.includes("option_signature !== 'default'") && editor.includes('$product->stock !== (int) $variant->stock')],
  ['Original allocation and net movement validated', editor.includes('allocate:v1:item:') && editor.includes("->sum('quantity_delta') !== -$expectedQuantity")],
  ['Money checks use integer poisha', editor.includes('ExactBdt::fromDecimal') && editor.includes('ExactBdt::multiply') && editor.includes('ExactBdt::toDecimal')],
  ['Server snapshots used, not client prices', controller.includes("'expected_quantity' => ['required'") && controller.includes("'quantity' => ['required'") && !controller.slice(controller.indexOf('public function updateManagedItemQuantity'), controller.indexOf('public function updateCourier')).includes('unit_price')],
  ['Conditional stock decrement prevents oversell', editor.includes("->where('stock', '>=', $delta)->decrement('stock', $delta)")],
  ['Stock restore bounded by unsigned capacity', editor.includes('4_294_967_295 - $restore')],
  ['Ledger and totals persisted in one DB transaction', editor.includes('DB::transaction(function') && editor.includes("'kind' => 'adjustment'") && editor.includes("'total_amount' => ExactBdt::toDecimal($newTotal)")],
  ['Admin & employee order scoping preserved', controller.includes('$this->ensureEmployeeOrderAccess($order);') && controller.includes('$this->adminOrEmployeeOnly();')],
  ['PATCH route stays in protected admin group', routes.includes("Route::patch('/{order}/managed-items/{item}/quantity'") && routes.includes("->name('managed_items.quantity')")],
  ['Dedicated Blade form CSRF & optimistic version', blade.includes("route('admin.orders.managed_items.quantity'") && blade.includes('@csrf') && blade.includes('@method(\'PATCH\')') && blade.includes('name="expected_quantity"')],
  ['Unsafe full edit UI submit disabled only in test mode', blade.includes('@if(!empty($p03QuantityUiEnabled)) disabled') && controller.includes('assertCanFullEdit')],
  ['Real MySQL runner needs rollback-only consent', smoke.includes("P03_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY'") && smoke.includes("require $root . '/tests/bootstrap-p03.php'")],
  ['Runner verifies synthetic rollback and stays empty', smoke.includes('$db->beginTransaction();') && smoke.includes('$db->rollBack();') && smoke.includes('Rollback left synthetic records')],
  ['Smoke covers stale, oversell, money, status, cancellation', ['Stale duplicate submission', 'Insufficient stock rejects', 'Corrupt saved price snapshot', 'Cancelled order cannot be edited', 'followed by cancellation'].every(x => smoke.includes(x))],
  ['Smoke has no schema commands or dangerous resets', !/artisan\s+(migrate|db:seed)|migrate:fresh|db:wipe|truncate\s+table/i.test(smoke)],
];

let fails = 0;
for (const [label, value] of checks) {
  console.log((value ? 'PASS ' : 'FAIL ') + label);
  if (!value) fails++;
}
console.log(`Result: ${checks.length - fails}/${checks.length} P03 quantity source QA ${fails ? 'FAILED' : 'PASS'} (no Laravel/MySQL runtime).`);
process.exitCode = fails ? 1 : 0;
