import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const controller = read('app/Http/Controllers/Admin/ProductVariantController.php');
const optController = read('app/Http/Controllers/Admin/ProductOptionController.php');
const catalog = read('app/Services/Catalog/VariantCatalogManager.php');
const routes = read('routes/admin.php');
const options = read('resources/views/admin/products/options.blade.php');
const variants = read('resources/views/admin/products/variants.blade.php');
const productEdit = read('app/Http/Controllers/Admin/ProductController.php');
const category = read('app/Http/Controllers/Admin/CategoryController.php');
const testLauncher = read('tools/qa/p04-mysql-catalog-smoke.php');
const spec = read('phpunit.p04.xml');

const checks = [
  ['P04 mutations require isolated local MySQL identity', catalog.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['P04 verifies required P02 schema', catalog.includes('Schema::hasTable($table)')],
  ['Four supported dimensions explicitly allowlisted', ['color', 'size', 'weight', 'piece_count'].every(x => catalog.includes(`'${x}' =>`))],
  ['Only approved option bootstrap and value creation', catalog.includes('bootstrapOptions()') && catalog.includes('addOptionValue(')],
  ['Duplicate options rejected server-side', catalog.includes('where(\'option_signature\', $signature)')],
  ['Duplicate SKU including archived variants rejected', catalog.includes('ProductVariant::withTrashed()->where(\'sku\', $sku)')],
  ['Maximum 200 variants per product', catalog.includes('count() >= 200')],
  ['Variant starts at zero inventory', catalog.includes("'stock' => 0,")],
  ['Managed product conversion requires zero stock', catalog.includes("(int) $variants->first()->stock !== 0")],
  ['Conversion disables public product before variable checkout', catalog.includes("'status' => false")],
  ['Only one opening movement for prepared legacy stock', catalog.includes('opening:v1:product:')],
  ['No direct overwrite of stock without ledger', catalog.includes('stock_after') && catalog.includes("'kind' => $kind")],
  ['Stock adjustment validates optimistic count', catalog.includes('$before !== $expectedStock')],
  ['Stock adjustment guards previous ledger', catalog.includes('(int) $last->stock_after !== $before')],
  ['Product mirror recomputed under transaction', catalog.includes("->sum('stock')") && catalog.includes("$locked->update(['stock' => $total])")],
  ['Invalid and cross-product variant update rejected', catalog.includes("->where('product_id', $locked->id)")],
  ['Product editing prevents silent managed stock overwrites', productEdit.includes('Managed stock is read-only here')],
  ['Legacy bulk activation prevents unreleased variable products', productEdit.includes("$action === 'active'") && productEdit.includes('variant-aware checkout')],
  ['Admin-only variants route', routes.includes("Route::middleware(['role:admin'])->group(function () {") && routes.includes("name('variants.stock')")],
  ['CSRF included on all new forms', options.includes('@csrf') && variants.includes('@csrf')],
  ['Only known option keys accepted', controller.includes('array:color,size,weight,piece_count')],
  ['Option value swatches hex-validated', optController.includes('0-9a-fA-F')],
  ['Option and variant mutations have no GET route', !routes.includes("Route::get('/{product}/variants/prepare'") && !routes.includes("Route::get('/options/bootstrap'")],
  ['Category cycle prevented', category.includes('assertNoParentCycle') && category.includes('ValidationException::withMessages')],
  ['Category hierarchy guarded by schema and isolation', category.includes('hierarchyAvailable()') && category.includes('P02MigrationSafety::assertIsolatedTestDatabase()')],
  ['Real MySQL suite requires explicit authorization', testLauncher.includes('P04_TEST_WRITE_APPROVED') && testLauncher.includes('YES_ROLLBACK_ONLY')],
  ['Real MySQL suite uses prebootstrap test-only gate', testLauncher.includes('tests/bootstrap-p03.php')],
  ['Real MySQL suite targets named test class only', testLauncher.includes('P04VariantCatalogMySqlTest')],
  ['No destructive schema commands in P04 launcher', !/(migrate:fresh|db:wipe|migrate:reset|db:seed|DROP TABLE)/i.test(testLauncher)],
  ['Test file uses DatabaseTransactions', read('tests/Feature/Catalog/P04VariantCatalogMySqlTest.php').includes('use DatabaseTransactions;')],
  ['P04 PHPUnit config selects only named test file', spec.includes('P04VariantCatalogMySqlTest.php') && spec.includes('bootstrap="tests/bootstrap-p03.php"')],
];
let passed = 0;
for (const [label, ok] of checks) {
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
  passed += ok ? 1 : 0;
}
console.log(`RESULT: ${passed}/${checks.length} P04 source contract checks ${passed === checks.length ? 'PASS' : 'FAIL'} (no DB access).`);
if (passed !== checks.length) process.exit(1);
