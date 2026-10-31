<?php

declare(strict_types=1);

/**
 * P11 read-only DATA readiness review on the exact isolated P02 test schema.
 * No Laravel boot, .env read, production database, SQL mutation, or deploy.
 */
if (PHP_SAPI !== 'cli' || getenv('P11_READ_ONLY_APPROVED') !== 'YES_ISOLATED_ONLY') {
    fwrite(STDERR, "BLOCKED: P11_READ_ONLY_APPROVED=YES_ISOLATED_ONLY is required.\n");
    exit(2);
}

require_once __DIR__ . '/P02TestSchemaGate.php';
require_once __DIR__ . '/P11ReadinessPolicy.php';

try {
    $root = dirname(__DIR__, 2);
    $pdo = P02TestSchemaGate::connectFromEnvironment();
    $schema = P02TestSchemaGate::evaluate(
        P02TestSchemaGate::sourceInventory($root), P02TestSchemaGate::inspect($pdo)
    );
    if (($schema['status'] ?? '') !== 'ALREADY_APPLIED') {
        throw new RuntimeException('P02 migration/schema review is not green on p01_campaign_test.');
    }

    // Only aggregate SELECT statements against a fixed, reviewed list of tables.
    // Never retrieve customer names, addresses, phone numbers, tokens or .env.
    $queries = [
        'active_legacy_products' => "SELECT COUNT(*) FROM products p
            WHERE p.status = 1 AND p.deleted_at IS NULL AND COALESCE(p.inventory_version, 0) <> 1",
        'active_managed_products' => "SELECT COUNT(*) FROM products p
            JOIN categories c ON c.id = p.category_id
            WHERE p.status = 1 AND p.deleted_at IS NULL AND p.inventory_version = 1
                AND c.status = 1 AND c.deleted_at IS NULL",
        'managed_stock_mismatches' => "SELECT COUNT(*) FROM products p
            WHERE p.inventory_version = 1 AND COALESCE(p.stock, 0) <>
                COALESCE((SELECT SUM(v.stock) FROM product_variants v
                    WHERE v.product_id = p.id AND v.deleted_at IS NULL), 0)",
        'invalid_simple_defaults' => "SELECT COUNT(*) FROM products p
            WHERE p.inventory_version = 1 AND p.has_variants = 0 AND p.deleted_at IS NULL
                AND ((SELECT COUNT(*) FROM product_variants v
                    WHERE v.product_id = p.id AND v.is_default = 1 AND v.is_active = 1
                        AND v.option_signature = 'default' AND v.deleted_at IS NULL) <> 1
                OR (SELECT COUNT(*) FROM product_variants v
                    WHERE v.product_id = p.id AND v.is_default = 1) <> 1)",
        'invalid_variable_options' => "SELECT COUNT(*) FROM products p
            WHERE p.status = 1 AND p.deleted_at IS NULL AND p.has_variants = 1
                AND (NOT EXISTS (SELECT 1 FROM product_option_assignments a
                    WHERE a.product_id = p.id)
                    OR NOT EXISTS (SELECT 1 FROM product_variants v
                        WHERE v.product_id = p.id AND v.is_active = 1 AND v.is_default = 0
                            AND v.deleted_at IS NULL
                            AND (SELECT COUNT(*) FROM product_variant_values vv
                                WHERE vv.product_variant_id = v.id) =
                                (SELECT COUNT(*) FROM product_option_assignments a
                                    WHERE a.product_id = p.id)
                            AND NOT EXISTS (
                                SELECT 1 FROM product_variant_values vv
                                JOIN product_option_values pov ON pov.id = vv.product_option_value_id
                                JOIN product_options po ON po.id = pov.product_option_id
                                WHERE vv.product_variant_id = v.id
                                    AND (pov.is_active <> 1 OR po.is_active <> 1
                                         OR NOT EXISTS (SELECT 1 FROM product_option_assignments a
                                             WHERE a.product_id = p.id
                                                 AND a.product_option_id = vv.product_option_id)))))",
        'broken_published_revisions' => "SELECT COUNT(*) FROM storefront_pages p
            LEFT JOIN storefront_revisions r ON r.id = p.published_revision_id
            WHERE p.published_revision_id IS NOT NULL AND (
                r.id IS NULL OR r.status <> 'published' OR r.storefront_page_id <> p.id)",
        'published_homepages' => "SELECT COUNT(*) FROM storefront_pages p
            JOIN storefront_revisions r ON r.id = p.published_revision_id
            WHERE p.mode = 'storefront' AND p.slug = 'home' AND r.status = 'published'
                AND r.storefront_page_id = p.id",
        'global_shipping_areas' => "SELECT COUNT(*) FROM shipping_charges
            WHERE campaign_id IS NULL AND status = 1 AND deleted_at IS NULL",
    ];

    $metrics = [];
    foreach ($queries as $key => $sql) {
        $metrics[$key] = (int) $pdo->query($sql)->fetchColumn();
        echo 'INFO ' . $key . '=' . $metrics[$key] . PHP_EOL;
    }
    $result = P11ReadinessPolicy::evaluate($metrics);
    echo 'STATUS: ' . $result['status'] . PHP_EOL;
    foreach ($result['blockers'] as $blocker) {
        echo 'BLOCKER ' . $blocker . PHP_EOL;
    }
    echo "P11 REVIEW: isolated data only, all queries read-only. Never a production approval.\n";
    exit($result['status'] === 'STAGING_DATA_CANDIDATE_ONLY' ? 0 : 3);
} catch (Throwable $exception) {
    // Do not print SQL exception details which can contain sensitive connection data.
    fwrite(STDERR, 'BLOCKED P11: isolated schema/SQL review failed (' . get_class($exception) . ").\n");
    exit(2);
}
