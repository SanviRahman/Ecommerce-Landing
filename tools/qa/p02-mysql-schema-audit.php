<?php

declare(strict_types=1);

require_once __DIR__ . '/P02TestSchemaGate.php';

/** Read-only: no Laravel bootstrap, migration, customer records, or table writes. */
try {
    $root = dirname(__DIR__, 2);
    $source = P02TestSchemaGate::sourceInventory($root);
    $pdo = P02TestSchemaGate::connectFromEnvironment();
    $snapshot = P02TestSchemaGate::inspect($pdo);
    $result = P02TestSchemaGate::evaluate($source, $snapshot);

    echo 'INFO historical migrations: ' . $result['legacy_applied'] . '/' . count($source['legacy']) . PHP_EOL;
    echo 'INFO P02 migrations: ' . $result['p02_applied'] . '/11' . PHP_EOL;
    echo 'INFO test schema tables: ' . $result['table_count'] . PHP_EOL;
    echo 'STATUS: ' . $result['status'] . PHP_EOL;
    echo 'DETAIL: ' . $result['reason'] . PHP_EOL;
    echo "READ-ONLY SCHEMA AUDIT COMPLETE. No DDL or business-data writes.\n";

    if ($result['status'] === 'BLOCKED') {
        exit(1);
    }
} catch (Throwable $exception) {
    // Intentionally do not echo PDO errors/DSN/password; report generic class only.
    fwrite(STDERR, 'BLOCKED: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
