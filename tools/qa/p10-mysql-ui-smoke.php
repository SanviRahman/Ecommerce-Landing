<?php

declare(strict_types=1);

/** Local-only P10 UI integration suite: all test fixture writes roll back. */
if (PHP_SAPI !== 'cli' || getenv('P10_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY') {
    fwrite(STDERR, "BLOCKED: P10_TEST_WRITE_APPROVED=YES_ROLLBACK_ONLY required.\n");
    exit(2);
}
$password = getenv('P02_TEST_DB_PASSWORD');
if (! is_string($password) || $password === '') {
    fwrite(STDERR, "BLOCKED: restricted test DB password missing.\n");
    exit(2);
}
$root = dirname(__DIR__, 2);
$phpunit = $root . '/vendor/phpunit/phpunit/phpunit';
if (! is_file($phpunit)) {
    fwrite(STDERR, "BLOCKED: PHPUnit missing. Install composer.lock dev dependencies first.\n");
    exit(2);
}
$settings = [
    'P03_ALLOW_MYSQL_TESTS' => 'yes-transaction-rollback-only',
    'P01_ALLOW_MYSQL_TESTS' => 'yes-disposable-only',
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
    'APP_URL' => 'http://127.0.0.1',
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306', 'DB_DATABASE' => 'p01_campaign_test',
    'DB_USERNAME' => 'p01_tester', 'DB_PASSWORD' => $password,
    'DB_URL' => '', 'DB_SOCKET' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'FILESYSTEM_DISK' => 'local', 'P07_CATALOG_TEST_ENABLED' => 'true',
];
unset($password);
foreach ($settings as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
unset($settings);
try {
    require $root . '/tests/bootstrap-p03.php';
    $db = P02TestSchemaGate::connectFromEnvironment();
    foreach ([
        'campaigns', 'products', 'orders', 'order_items', 'customers',
        'site_settings', 'carts', 'cart_items', 'checkout_requests',
        'storefront_pages', 'storefront_revisions', 'homepage_sections',
    ] as $table) {
        if ((int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() !== 0) {
            throw new RuntimeException('P10 synthetic test requires an empty commerce/test schema: ' . $table);
        }
    }
    unset($db);
    $process = proc_open([
        PHP_BINARY, $phpunit,
        '--configuration=' . $root . '/phpunit.p10.xml',
        '--filter=P10StorefrontIntegrationMySqlTest', '--fail-on-empty-test-suite', '--no-progress',
    ], [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => STDOUT,
        2 => STDERR,
    ], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('P10 test runner did not start.');
    }
    $result = proc_close($process);
    if ($result !== 0) {
        throw new RuntimeException('P10 MySQL suite failed: ' . $result);
    }
    echo "RESULT: P10 authenticated UI integration MySQL checks PASS (all synthetic transactions rolled back).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL P10: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
