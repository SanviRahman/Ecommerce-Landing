<?php

declare(strict_types=1);

/**
 * Read-only P09 browser-CSRF pre/postflight. Does not boot Laravel or read .env.
 * Rejects populated business tables so invalid POSTs never test on customer data.
 */
if (PHP_SAPI !== 'cli' || getenv('P09_HTTP_CSRF_GUARD') !== 'YES_READ_ONLY') {
    fwrite(STDERR, "BLOCKED: P09 read-only browser probe guard approval is missing.\n");
    exit(2);
}

require_once __DIR__ . '/P02TestSchemaGate.php';

try {
    $root = dirname(__DIR__, 2);
    $db = P02TestSchemaGate::connectFromEnvironment();
    $schema = P02TestSchemaGate::evaluate(
        P02TestSchemaGate::sourceInventory($root), P02TestSchemaGate::inspect($db)
    );
    if (($schema['status'] ?? '') !== 'ALREADY_APPLIED') {
        throw new RuntimeException('P02 schema must already be fully applied on the isolated test database.');
    }
    $tables = [
        'campaigns', 'products', 'orders', 'order_items', 'customers',
        'carts', 'cart_items', 'checkout_requests', 'inventory_movements',
        'storefront_pages', 'storefront_revisions', 'homepage_sections',
    ];
    foreach ($tables as $table) {
        $count = (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        if ($count !== 0) {
            throw new RuntimeException('Test schema is not empty (' . $table . '). No browser probe permitted.');
        }
    }
    echo "PASS P09 restricted test identity, P02 schema, and empty commerce/cart tables (read-only)\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'BLOCKED P09: ' . $exception->getMessage() . "\n");
    exit(2);
}
