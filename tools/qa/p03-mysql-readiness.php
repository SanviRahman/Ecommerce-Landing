<?php

declare(strict_types=1);

require_once __DIR__ . '/P02TestSchemaGate.php';

/**
 * Read-only, count-only review of the disposable P03 test schema.
 * Does not query customer/identity fields, boot Laravel, run seeders, or modify
 * inventory. A warehouse stock snapshot is NOT proof of sellable availability.
 */
try {
    $root = dirname(__DIR__, 2);
    $pdo = P02TestSchemaGate::connectFromEnvironment();
    $source = P02TestSchemaGate::sourceInventory($root);
    $state = P02TestSchemaGate::evaluate($source, P02TestSchemaGate::inspect($pdo));
    if ($state['status'] !== 'ALREADY_APPLIED') {
        throw new RuntimeException('P02 test schema is not completely applied. Status: ' . $state['status']);
    }

    $counts = [
        'all_products' => "SELECT COUNT(*) FROM products",
        'legacy_products' => "SELECT COUNT(*) FROM products WHERE inventory_version = 0",
        'managed_products' => "SELECT COUNT(*) FROM products WHERE inventory_version = 1",
        'variant_products' => "SELECT COUNT(*) FROM products WHERE has_variants = 1",
        'default_variants' => "SELECT COUNT(*) FROM product_variants WHERE is_default = 1",
        'opening_movements' => "SELECT COUNT(*) FROM inventory_movements WHERE kind = 'opening'",
        'managed_orders' => "SELECT COUNT(*) FROM orders WHERE inventory_version = 1",
        'invalid_managed_defaults' => "SELECT COUNT(*) FROM products p
            LEFT JOIN (
                SELECT product_id,
                    SUM(CASE WHEN is_default = 1 AND is_active = 1
                        AND deleted_at IS NULL AND option_signature = 'default' THEN 1 ELSE 0 END) AS active_defaults,
                    SUM(CASE WHEN is_default = 1 THEN 1 ELSE 0 END) AS all_defaults
                FROM product_variants GROUP BY product_id
            ) v ON v.product_id = p.id
            WHERE p.inventory_version = 1 AND p.has_variants = 0
                AND (COALESCE(v.active_defaults, 0) <> 1 OR COALESCE(v.all_defaults, 0) <> 1)",
        'managed_stock_mismatch' => "SELECT COUNT(*) FROM products p
            LEFT JOIN (SELECT product_id, SUM(stock) AS total_stock
                FROM product_variants WHERE deleted_at IS NULL GROUP BY product_id) v ON v.product_id = p.id
            WHERE p.inventory_version = 1 AND p.has_variants = 0
                AND p.stock <> COALESCE(v.total_stock, 0)",
        'missing_opening_movement' => "SELECT COUNT(*) FROM products p
            WHERE p.inventory_version = 1 AND p.has_variants = 0
                AND NOT EXISTS (SELECT 1 FROM inventory_movements m
                    WHERE m.operation_key = CONCAT('opening:v1:product:', p.id))",
    ];
    $results = [];
    foreach ($counts as $label => $query) {
        $results[$label] = (int) $pdo->query($query)->fetchColumn();
        echo 'INFO ' . $label . ': ' . $results[$label] . PHP_EOL;
    }

    if ($results['invalid_managed_defaults'] > 0 || $results['managed_stock_mismatch'] > 0
        || $results['missing_opening_movement'] > 0) {
        $status = 'BLOCKED_INCONSISTENT_MANAGED_STOCK';
    } elseif ($results['legacy_products'] > 0) {
        $status = 'BACKFILL_REVIEW_REQUIRED';
    } elseif ($results['all_products'] === 0) {
        $status = 'EMPTY_CATALOG_NEEDS_SYNTHETIC_FIXTURES';
    } else {
        $status = 'READY_FOR_ISOLATED_P03_TESTS';
    }

    echo 'STATUS: ' . $status . PHP_EOL;
    echo "READ-ONLY P03 READINESS COMPLETE. No SQL writes, migrations or Laravel boot.\n";
    if (str_starts_with($status, 'BLOCKED')) {
        exit(1);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'BLOCKED: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
