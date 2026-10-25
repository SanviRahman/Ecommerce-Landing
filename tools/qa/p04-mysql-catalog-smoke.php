<?php

declare(strict_types=1);

/**
 * Run only the P04 test class against an EMPTY, isolated p01_campaign_test schema.
 * Every fixture, actor, SKU and ledger movement is rolled back.
 */
if (PHP_SAPI !== 'cli' || getenv('P04_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY') {
    fwrite(STDERR, "BLOCKED: P04_TEST_WRITE_APPROVED=YES_ROLLBACK_ONLY required.\n");
    exit(2);
}
$password = getenv('P02_TEST_DB_PASSWORD');
if (! is_string($password) || $password === '') {
    fwrite(STDERR, "BLOCKED: restricted test-user password missing.\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$phpunit = $root . '/vendor/phpunit/phpunit/phpunit';
if (! is_file($phpunit)) {
    fwrite(STDERR, "BLOCKED: PHPUnit dev dependencies missing. Install from composer.lock.\n");
    exit(2);
}

$settings = [
    'P03_ALLOW_MYSQL_TESTS' => 'yes-transaction-rollback-only',
    'P01_ALLOW_MYSQL_TESTS' => 'yes-disposable-only',
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
    'APP_URL' => 'http://127.0.0.1',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'p01_campaign_test',
    'DB_USERNAME' => 'p01_tester',
    'DB_PASSWORD' => $password,
    'DB_URL' => '',
    'DB_SOCKET' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'FILESYSTEM_DISK' => 'local',
];
unset($password);
foreach ($settings as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}
unset($settings);

try {
    // Pre-Laravel read-only gate rejects non-empty commerce schemas.
    require $root . '/tests/bootstrap-p03.php';
    // In addition to the P03 commerce-data gate, require empty P04 definition
    // tables to prevent fixture collisions with manually created option values.
    $pdo = P02TestSchemaGate::connectFromEnvironment();
    foreach (['product_options', 'product_option_values', 'product_variants',
        'product_variant_values', 'product_option_assignments', 'inventory_movements'] as $table) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() !== 0) {
            throw new RuntimeException('P04 suite requires an empty, isolated catalog test schema.');
        }
    }
    unset($pdo);
    $command = [PHP_BINARY, $phpunit,
        '--configuration=' . $root . '/phpunit.p04.xml',
        '--filter=P04VariantCatalogMySqlTest', '--no-progress'];
    $process = proc_open($command, [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => STDOUT, 2 => STDERR,
    ], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start guarded PHPUnit.');
    }
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException('P04 test suite failed, exit ' . $exit);
    }
    echo "RESULT: P04 catalog MySQL suite PASS (all fixture transactions rolled back).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL P04: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
