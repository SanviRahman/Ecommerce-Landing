import assert from 'node:assert/strict';
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const get = path => readFileSync(resolve(root, path), 'utf8');
const src = get('app/Services/Inventory/InventoryAllocator.php');
const flag = get('config/storefront.php');
const p02 = get('app/Support/P02MigrationSafety.php');
const idem = get('app/Services/Checkout/CheckoutIdempotencyService.php');
const unchanged = [
  'app/Http/Controllers/Admin/CampaignOrderController.php',
  'app/Http/Controllers/Admin/OrderController.php',
  'app/Http/Controllers/Admin/BulkOrderEntryController.php',
  'app/Services/OrderManagementService.php',
];
for (const path of unchanged) assert.ok(existsSync(resolve(root, path)), `Missing legacy writer ${path}`);
const checks = [
  ['feature flag off by default', /'p03_core_test_enabled'\s*=>\s*false/.test(flag)],
  ['feature flag checked', src.includes("config('storefront.p03_core_test_enabled', false) !== true")],
  ['P02 isolated database gate', src.includes('P02MigrationSafety::assertIsolatedTestDatabase()') && p02.includes('p01_campaign_test')],
  ['existing transaction required', src.includes('DB::transactionLevel() < 1')],
  ['product rows locked', /products'\).*?lockForUpdate\(\)/s.test(src)],
  ['variant rows locked', /product_variants'\).*?lockForUpdate\(\)/s.test(src)],
  ['stock checks reject oversell', src.includes('InventoryPolicy::assertPurchasable')],
  ['conditional stock decrement', src.includes("->where('stock', '>=', $quantity)->decrement")],
  ['operation-key deduplication', src.includes("'allocate:v1:item:'") && src.includes("where('operation_key', $operationKey)")],
  ['legacy order protection', src.includes('inventory_version') && src.includes('Legacy, deleted and missing orders')],
  ['price derived from campaign-product pivot', src.includes("DB::table('campaign_product')")],
  ['exact decimal price verification', src.includes('ExactBdt::fromDecimal') && src.includes('ExactBdt::multiply')],
  ['release is explicit pre-dispatch only', src.includes('InventoryPolicy::assertCanRelease')],
  ['no external order auto-allocation', src.includes("$order->created_via === 'external_api'")],
  ['legacy order writer paths remain present', unchanged.every(path => existsSync(resolve(root, path)))],
  ['idempotency uses unique owner and request keys', idem.includes("where('owner_hash', $ownerHash)") && idem.includes("where('idempotency_key', $idempotencyKey)")],
  ['idempotency protects request fingerprints and scope', idem.includes('request_hash !== $requestHash') && idem.includes('$existing->channel !== $channel')],
  ['idempotency claim/completion bound to transaction', idem.includes('DB::transactionLevel() < 1') && idem.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['checkout completion needs managed inventory', idem.includes('(int) $order->inventory_version !== 1')],
  ['preflight is read-only and standalone', get('tools/qa/p02-mysql-preflight.php').includes('USE `ecommerce_landing_db`')],
];
let passed = 0;
for (const [name, ok] of checks) {
  assert.ok(ok, name);
  console.log('PASS', name);
  passed++;
}
console.log(`Result: ${passed}/${checks.length} P03 SOURCE contract checks pass; NO DB/integration tests run`);
