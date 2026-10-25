<?php

declare(strict_types=1);

/**
 * Read-only local QA login preflight on the restricted P02 schema.
 * Does not inspect or change original database, passwords, or user rows.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "BLOCKED: CLI only.\n");
    exit(2);
}

require_once __DIR__ . '/P02TestSchemaGate.php';

try {
    $db = P02TestSchemaGate::connectFromEnvironment();
    foreach (['users', 'storefront_pages', 'storefront_revisions', 'homepage_sections'] as $table) {
        $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $statement->execute([$table]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('Required P02 test table is missing: ' . $table);
        }
    }

    // Only clearly synthetic local QA admins are allowed; no real user details.
    $unexpectedAdmins = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND email NOT LIKE '%@example.test'")
        ->fetchColumn();
    if ($unexpectedAdmins > 0) {
        throw new RuntimeException('Unexpected non-synthetic admin exists in the restricted test schema. Stop.');
    }
    $count = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND email LIKE '%@example.test' AND is_active = 1 AND deleted_at IS NULL")
        ->fetchColumn();
    if ($count === 0) {
        fwrite(STDERR, "BLOCKED_NO_ISOLATED_ADMIN: No active synthetic admin exists in p01_campaign_test.\n");
        fwrite(STDERR, "Do not copy or seed a real admin. Obtain explicit approval before creating a disposable test-only admin fixture.\n");
        exit(3);
    }
    echo "PASS P13: isolated homepage schema exists and a test-schema admin account is available.\n";
    echo "READ-ONLY: original ecommerce_landing_db was not accessed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'BLOCKED P13: ' . $e->getMessage() . "\n");
    exit(2);
}
