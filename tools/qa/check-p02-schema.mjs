/**
 * Dependency-free P02 SOURCE CONTRACT QA.
 * These assertions do NOT execute real MySQL migrations or checkout flows.
 */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const migrationDir = path.join(root, 'database', 'migrations');
const files = fs.readdirSync(migrationDir)
  .filter((file) => /^2026_10_24_21\d{4}_p02_.*\.php$/.test(file))
  .sort();
const expected = Array.from({length: 11}, (_, i) => `2026_10_24_${String(210001+i).padStart(6,'0')}_`);
assert.equal(files.length, 11, 'P02 must contain exactly 11 ordered migrations');
const text = files.map((file, i) => {
  assert.ok(file.startsWith(expected[i]), 'P02 migration ordering mismatch: '+file);
  const code = fs.readFileSync(path.join(migrationDir, file), 'utf8');
  assert.match(code, /public function up\(\): void\s*\{\s*P02MigrationSafety::assertApprovedP02Up\(\);/, file+' missing explicit up guard');
  assert.match(code, /public function down\(\): void\s*\{\s*P02MigrationSafety::assertIsolatedTestDatabase\(\);/, file+' missing down guard');
  const up = code.split('public function up(): void')[1]?.split('public function down(): void')[0] ?? '';
  assert.doesNotMatch(up, /Schema::drop|DB::statement\s*\(\s*['"`]\s*(?:DROP|TRUNCATE|DELETE|UPDATE)/i, file+' contains potential destructive SQL');
  return code;
});
console.log('PASS 11 ordered P02 migrations with entry-point isolation guards');

const expectedTables = [
  'product_options', 'product_option_values', 'product_option_assignments',
  'product_variants', 'product_variant_values', 'inventory_movements',
  'checkout_requests', 'storefront_pages', 'storefront_revisions',
  'homepage_sections', 'homepage_section_products', 'homepage_section_categories',
  'homepage_section_items', 'homepage_audit_events', 'carts', 'cart_items',
];
const all = text.join('\n');
for (const table of expectedTables) {
  assert.ok(text.some(code => code.includes(`Schema::create('${table}'`)), `Missing ${table}`);
}
console.log(`PASS ${expectedTables.length} new schema tables defined`);

const fkTargets = [...all.matchAll(/->constrained\('([^']+)'\)/g)].map((m) => m[1]);
for (const target of fkTargets) {
  assert.ok(expectedTables.includes(target) || ['categories','products','orders','order_items','campaigns','users','reviews','media'].includes(target), `Unexpected foreign key target: ${target}`);
}
console.log('PASS foreign key targets belong to established/new schema');

for (const [table, required] of [
  ['categories', ['parent_id','sort_order']],
  ['products', ['has_variants','inventory_version']],
  ['orders', ['inventory_version','sub_total','shipping_charge','cod_charge','total_amount']],
  ['order_items', ['product_variant_id','variant_sku','variant_options','inventory_allocated_quantity']],
]) {
  assert.ok(all.includes(`Schema::table('${table}'`), `Missing extension ${table}`);
  for (const col of required) assert.ok(all.includes(`'${col}'`), `Missing ${table}.${col}`);
}
assert.match(all,/Money widening is forward-only/);
console.log('PASS legacy column extensions and forward-only money widening');

const safety = fs.readFileSync(path.join(root,'app','Support','P02MigrationSafety.php'),'utf8');
for (const s of ['p01_campaign_test','p01_tester','127.0.0.1','3306','CURRENT_USER()','DATABASE()','environment([\'local\', \'testing\'])']) {
  assert.ok(safety.includes(s), `P02 migration protection missing ${s}`);
}
const command = fs.readFileSync(path.join(root,'app','Console','Commands','BackfillDefaultProductVariants.php'),'utf8');
for (const s of ['{--apply','{--acknowledge=',"'opening:v1:product:'",'lockForUpdate()','DB::transaction(',"'inventory_version' => 1",'p01_campaign_test','p01_tester','return \'LEGACY-\' . $productId']) {
  assert.ok(command.includes(s), `Default variant backfill missing safety contract: ${s}`);
}
assert.match(command,/\$dryRun\s*=\s*!\s*\$apply;/);
console.log('PASS explicit test-schema allowlist and dry-run backfill contract');

const routes = fs.readFileSync(path.join(root,'routes','web.php'),'utf8');
assert.match(routes, /require __DIR__ \. '\/user\.php';/);
const userRoutes = fs.readFileSync(path.join(root,'routes','user.php'),'utf8');
assert.ok(userRoutes.includes('HomeController'));
const homepage = fs.readFileSync(path.join(root,'resources','views','frontend','pages','home.blade.php'),'utf8');
assert.match(homepage,/frontend\/js\/home\.js/);
console.log('PASS existing campaign-home frontend route/asset hooks remain present');
console.log('Result: P02 static schema/source contract checks PASS (not a MySQL migration test).');
