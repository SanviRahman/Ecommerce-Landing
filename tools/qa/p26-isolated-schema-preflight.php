<?php

declare(strict_types=1);

/**
 * P26 isolated-functional-preview schema preflight: READ-ONLY, no migrations.
 * Never connects to ecommerce_landing_db or displays credentials/customer data.
 */
require_once __DIR__ . '/P02TestSchemaGate.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "BLOCKED P26: CLI only.\n");
    exit(2);
}

try {
    $pdo = P02TestSchemaGate::connectFromEnvironment();
    $snapshot = P02TestSchemaGate::inspect($pdo);
    $tables = array_fill_keys($snapshot['tables'], true);
    $requiredTables = [
        'storefront_accounts', 'storefront_pages', 'storefront_revisions',
        'homepage_sections', 'categories', 'products', 'product_variants',
        'product_option_assignments', 'product_option_values', 'product_options',
        'product_variant_values', 'shipping_charges', 'carts', 'cart_items',
        'checkout_requests', 'inventory_movements', 'orders', 'order_items',
        'customers', 'users',
    ];
    $missing = array_values(array_filter($requiredTables, static fn (string $table): bool => ! isset($tables[$table])));
    if ($missing !== []) {
        throw new RuntimeException('Schema is missing reviewed storefront tables: ' . implode(', ', $missing));
    }
    if (! isset($snapshot['columns']['orders']['storefront_account_id'])) {
        throw new RuntimeException('The isolated test database is missing the customer-account migration.');
    }
    $requiredMigration = '2026_10_26_100001_create_storefront_accounts';
    if (! in_array($requiredMigration, $snapshot['applied'], true)) {
        throw new RuntimeException('The isolated customer-account migration is not recorded as applied.');
    }
    echo "PASS P26 isolated storefront schema and storefront_accounts migration\n";
    echo "READ-ONLY: no rows modified. Check P11 inventory readiness separately.\n";
} catch (Throwable $exception) {
    // Safe schema/table names only. No raw PDO exception, credentials, or row values.
    $message = $exception instanceof RuntimeException ? $exception->getMessage() : get_class($exception);
    fwrite(STDERR, 'BLOCKED P26 schema: ' . $message . "\n");
    exit(2);
}
