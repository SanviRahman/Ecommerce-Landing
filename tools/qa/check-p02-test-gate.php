<?php

declare(strict_types=1);

require_once __DIR__ . '/P02TestSchemaGate.php';

$root = dirname(__DIR__, 2);
$source = P02TestSchemaGate::sourceInventory($root);
$assertions = 0;

$check = static function (bool $ok, string $label) use (&$assertions): void {
    if (! $ok) {
        fwrite(STDERR, "FAIL {$label}\n");
        exit(1);
    }
    $assertions++;
    echo "PASS {$label}\n";
};

$check(count($source['legacy']) > 60, 'Historic migration inventory is present');
$check(count($source['p02']) === 11, 'Expected P02 migration count');
$check(count($source['new_tables']) === 16, 'Expected new-table count');

$cols = [];
foreach (['categories', 'products', 'orders', 'order_items'] as $table) {
    $cols[$table] = [];
}
$legacyTables = [
    'migrations', 'categories', 'products', 'campaigns', 'campaign_product',
    'orders', 'order_items', 'users',
];
$base = ['applied' => $source['legacy'], 'tables' => $legacyTables, 'columns' => $cols];
$evaluate = static fn (array $data) => P02TestSchemaGate::evaluate($source, $data)['status'];

$check($evaluate($base) === 'READY_TO_TEST_MIGRATE', 'Complete legacy baseline is ready');
$oldMissing = $base;
array_pop($oldMissing['applied']);
$check($evaluate($oldMissing) === 'BLOCKED', 'Incomplete old migrations fail closed');
$unknown = $base;
$unknown['applied'][] = 'unexpected_history_entry';
$check($evaluate($unknown) === 'BLOCKED', 'Unknown applied migration fails closed');
$partial = $base;
$partial['applied'][] = $source['p02'][0];
$check($evaluate($partial) === 'BLOCKED', 'Partial P02 migration fails closed');
$orphan = $base;
$orphan['tables'][] = $source['new_tables'][0];
$check($evaluate($orphan) === 'BLOCKED', 'Orphan P02 table fails closed');
$orphanCol = $base;
$orphanCol['columns']['products']['has_variants'] = ['type' => 'tinyint', 'precision' => 3, 'scale' => 0];
$check($evaluate($orphanCol) === 'BLOCKED', 'Orphan P02 column fails closed');
$missingTable = $base;
$missingTable['tables'] = ['migrations', 'products'];
$check($evaluate($missingTable) === 'BLOCKED', 'Missing historical table fails closed');

$after = $base;
$after['applied'] = array_merge($source['legacy'], $source['p02']);
$after['tables'] = array_merge($legacyTables, $source['new_tables']);
foreach ([
    'categories' => ['parent_id', 'sort_order'],
    'products' => ['has_variants', 'inventory_version'],
    'orders' => ['inventory_version'],
    'order_items' => ['product_variant_id', 'variant_sku', 'variant_options', 'inventory_allocated_quantity'],
] as $table => $fields) {
    foreach ($fields as $field) {
        $after['columns'][$table][$field] = ['type' => 'int', 'precision' => 0, 'scale' => 0];
    }
}
foreach ([
    'orders' => ['sub_total', 'shipping_charge', 'cod_charge', 'total_amount'],
    'order_items' => ['unit_price', 'total_price', 'discount_amount'],
] as $table => $fields) {
    foreach ($fields as $field) {
        $after['columns'][$table][$field] = ['type' => 'decimal', 'precision' => 14, 'scale' => 2];
    }
}
$check($evaluate($after) === 'ALREADY_APPLIED', 'Fully applied P02 is recognized');
$wrongMoney = $after;
$wrongMoney['columns']['orders']['sub_total']['scale'] = 0;
$check($evaluate($wrongMoney) === 'BLOCKED', 'Incorrect money precision fails closed');

$check(! str_contains(file_get_contents(__DIR__ . '/p02-mysql-schema-audit.php'), 'artisan migrate'), 'Audit script cannot run Laravel migration');
echo "Result: {$assertions}/{$assertions} isolated P02 migration-gate tests PASS (no DB connection)\n";
