<?php

declare(strict_types=1);

/**
 * Pre-Laravel guard for P03 MySQL integration tests.
 * No Laravel bootstrap or fixture writing may occur before this guard passes.
 */
$required = [
    'P03_ALLOW_MYSQL_TESTS' => 'yes-transaction-rollback-only',
    'P01_ALLOW_MYSQL_TESTS' => 'yes-disposable-only',
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'p01_campaign_test',
    'DB_USERNAME' => 'p01_tester',
];
foreach ($required as $name => $value) {
    if ((string) getenv($name) !== $value) {
        throw new RuntimeException('P03 integration blocked: unexpected ' . $name);
    }
}

if (getenv('DB_URL') || getenv('DB_SOCKET')) {
    throw new RuntimeException('P03 integration blocked: DB_URL and DB_SOCKET must be empty.');
}
if (getenv('P02_TEST_DB_PASSWORD') === false || getenv('DB_PASSWORD') === false
    || ! hash_equals((string) getenv('P02_TEST_DB_PASSWORD'), (string) getenv('DB_PASSWORD'))) {
    throw new RuntimeException('P03 integration blocked: test-only DB credentials are missing or inconsistent.');
}

require_once __DIR__ . '/../tools/qa/P02TestSchemaGate.php';

$connection = P02TestSchemaGate::connectFromEnvironment();
$inventory = P02TestSchemaGate::sourceInventory(dirname(__DIR__));
$status = P02TestSchemaGate::evaluate($inventory, P02TestSchemaGate::inspect($connection));
if ($status['status'] !== 'ALREADY_APPLIED') {
    throw new RuntimeException('P03 integration blocked: P02 schema is not fully applied.');
}

// No production-looking commerce data may be mixed with synthetic fixtures.
foreach (['products', 'orders', 'order_items', 'campaigns', 'customers'] as $table) {
    if ((int) $connection->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() > 0) {
        throw new RuntimeException('P03 integration blocked: the test schema already contains commerce records.');
    }
}
unset($connection);

require_once __DIR__ . '/bootstrap-p01.php';
