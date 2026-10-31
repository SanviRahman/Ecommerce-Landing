<?php

declare(strict_types=1);

/**
 * Read-only, two-connection MariaDB row-lock probe. Tests InnoDB's lock
 * arbitration on an existing test-only migration metadata row. This is NOT
 * a last-item purchase race test and performs no business-data writes.
 */

if (PHP_SAPI !== 'cli' || getenv('P03_TEST_LOCK_PROBE_APPROVED') !== 'YES_READ_ONLY_LOCK_PROBE') {
    fwrite(STDERR, "BLOCKED: P03_TEST_LOCK_PROBE_APPROVED=YES_READ_ONLY_LOCK_PROBE required.\n");
    exit(2);
}
if (! is_string(getenv('P02_TEST_DB_PASSWORD')) || getenv('P02_TEST_DB_PASSWORD') === '') {
    fwrite(STDERR, "BLOCKED: Restricted test account password must be supplied in the process environment.\n");
    exit(2);
}

require __DIR__ . '/P02TestSchemaGate.php';

$a = null;
$b = null;
try {
    $a = P02TestSchemaGate::connectFromEnvironment();
    $b = P02TestSchemaGate::connectFromEnvironment();
    $root = dirname(__DIR__, 2);
    $inventory = P02TestSchemaGate::sourceInventory($root);
    $aStatus = P02TestSchemaGate::evaluate($inventory, P02TestSchemaGate::inspect($a));
    $bStatus = P02TestSchemaGate::evaluate($inventory, P02TestSchemaGate::inspect($b));
    if ($aStatus['status'] !== 'ALREADY_APPLIED' || $bStatus['status'] !== 'ALREADY_APPLIED') {
        throw new RuntimeException('The isolated P02 schema must be fully applied on BOTH connections.');
    }
    if ((string) $a->query('SELECT DATABASE()')->fetchColumn() !== 'p01_campaign_test'
        || (string) $b->query('SELECT DATABASE()')->fetchColumn() !== 'p01_campaign_test') {
        throw new RuntimeException('Unexpected database context.');
    }
    $id = (int) $a->query('SELECT id FROM migrations ORDER BY id LIMIT 1')->fetchColumn();
    if ($id < 1) {
        throw new RuntimeException('No test migration metadata is available for the lock probe.');
    }
    $engine = (string) $a->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'p01_campaign_test' AND TABLE_NAME = 'migrations'")->fetchColumn();
    if (strtoupper($engine) !== 'INNODB') {
        throw new RuntimeException('The lock probe needs an InnoDB migration metadata table.');
    }

    $b->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $a->beginTransaction();
    $first = $a->prepare('SELECT id FROM migrations WHERE id = ? FOR UPDATE');
    $first->execute([$id]);
    if ((int) $first->fetchColumn() !== $id) {
        throw new RuntimeException('Primary connection could not acquire its row lock.');
    }
    echo "PASS connection A holds an InnoDB row lock in a transaction\n";

    $b->beginTransaction();
    $blocked = false;
    try {
        $second = $b->prepare('SELECT id FROM migrations WHERE id = ? FOR UPDATE');
        $second->execute([$id]);
        $second->fetchColumn();
    } catch (PDOException $exception) {
        $dbCode = (int) ($exception->errorInfo[1] ?? 0);
        if ($dbCode !== 1205) {
            throw $exception;
        }
        $blocked = true;
    }
    if (! $blocked) {
        throw new RuntimeException('Connection B unexpectedly bypassed connection A row lock.');
    }
    echo "PASS connection B cannot acquire the locked row (MariaDB error 1205)\n";
    $b->rollBack();
    $a->rollBack();

    $b->beginTransaction();
    $after = $b->prepare('SELECT id FROM migrations WHERE id = ? FOR UPDATE');
    $after->execute([$id]);
    if ((int) $after->fetchColumn() !== $id) {
        throw new RuntimeException('The row was not available after lock release.');
    }
    echo "PASS connection B acquires the lock after A rolls back\n";
    $b->rollBack();
    echo "RESULT: 3/3 REAL MYSQL TWO-CONNECTION LOCK PROBE PASS (read-only, both transactions rolled back).\n";
    echo "NOTE: This confirms database lock contention, NOT concurrent last-item purchase protection.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL P03 lock probe: ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    foreach ([$b, $a] as $connection) {
        if ($connection instanceof PDO) {
            try {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
            } catch (Throwable) {
                // Never mask the original assertion failure.
            }
        }
    }
}
