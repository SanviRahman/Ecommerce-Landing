import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const read = (name) => fs.readFileSync(path.join(root, name), 'utf8');
const checkout = read('app/Services/Checkout/StorefrontCheckout.php');
const race = read('tools/qa/p09-checkout-race-smoke.php');
const worker = read('tools/qa/p09-checkout-race-worker.php');
const p08Test = read('tests/Feature/Storefront/P08GuestCartCheckoutMySqlTest.php');
const csrf = read('tools/qa/p09-browser-csrf-smoke.ps1');
const checks = [
 ['Checkout locks owned cart BEFORE idempotency claim', () => checkout.includes("->where('owner_hash', $ownerHash)->lockForUpdate()->first(['id'])") && checkout.indexOf("->where('owner_hash', $ownerHash)->lockForUpdate()->first(['id'])") < checkout.indexOf('$this->idempotency->claim(')],
 ['Request hash is built under cart lock from persisted cart ID', () => checkout.indexOf("'cart_id' => (string) $cartIdentity->id") > checkout.indexOf("->lockForUpdate()->first(['id'])") && checkout.indexOf("'cart_id' => (string) $cartIdentity->id") < checkout.indexOf('$this->idempotency->claim(')],
 ['Checkout replay still precedes cart version check', () => checkout.indexOf("$claim['state'] === 'replayed'") < checkout.indexOf('assertOwnedCart($ownerHash, $expected)')],
 ['Original combined order/stock transaction preserved', () => checkout.includes('DB::transaction(function ()') && checkout.includes('allocateStorefrontOrder(') && checkout.includes('$this->idempotency->complete(')],
 ['Race CLI requires separate explicit approval', () => race.includes("P09_RACE_WRITE_APPROVED") && race.includes('YES_SYNTHETIC_COMMIT_AND_CLEANUP')],
 ['Race test uses restricted MySQL and P02 schema gate', () => race.includes('P02TestSchemaGate::connectFromEnvironment()') && race.includes('P02TestSchemaGate::evaluate(')],
 ['Race refuses nonempty business data', () => race.includes("'products'") && race.includes("'orders'") && race.includes("'customers'") && race.includes("'carts'")],
 ['Two independent PHP workers execute actual checkout service', () => race.includes('proc_open(') && worker.includes('StorefrontCheckout::class') && worker.includes('->place(')],
 ['Gate starts both workers before releasing stock contention', () => race.includes('P09_RACE_GATE') && worker.includes('P09_RACE_GATE') && race.includes('file_put_contents($gate')],
 ['Committed fixture cleanup uses bounded IDs and explicit transaction', () => race.includes('function cleanupFixture(') && race.includes('beginTransaction()') && race.includes('rollBack()') && race.includes('commit()')],
 ['Worker suppresses external order sync in synthetic test', () => worker.includes('Order::unsetEventDispatcher()')],
 ['Worker reports synthetic-only outcomes, not raw identities', () => worker.includes('P09_RESULT:') && !worker.includes('echo $ownerHash')],
 ['Race test requires exactly one success and final stock zero', () => race.includes("$successes !== 1") && race.includes("->fetchColumn() !== 0")],
 ['Race test verifies loser retains cart and one allocation', () => race.includes('losing cart') && race.includes('inventory_movements')],
 ['Rollback-only P08 suite covers missing cart and replay scope', () => p08Test.includes('test_missing_cart_cannot_reserve_checkout_key') && p08Test.includes('test_reusing_key_after_cart_identity_changes_is_refused')],
 ['Real HTTP probe uses only invalid product input', () => csrf.includes("$payload.Add('product_variant_id','0')") && csrf.includes("Missing-CSRF POST rejected with 419")],
 ['HTTP probe forces isolated DB and restores all original process env', () => csrf.includes("$env:DB_DATABASE = 'p01_campaign_test'") && csrf.includes("[Environment]::SetEnvironmentVariable($key, $previous[$key], 'Process')")],
 ['No migration, reset, truncate, or seeding in runner', () => !/migrate:fresh|db:wipe|db:seed|\bTRUNCATE\b|artisan\s+.*migrate/i.test(race + worker)],
];
let pass = 0;
for (const [name, fn] of checks) {
 let ok = false; try { ok = Boolean(fn()); } catch {};
 console.log(`${ok ? 'PASS' : 'FAIL'} ${name}`); if(ok) pass++;
}
console.log(`RESULT: ${pass}/${checks.length} P09 race/checkout source checks ${pass===checks.length?'PASS':'FAIL'} (offline only)`);
if (pass !== checks.length) process.exitCode = 1;
