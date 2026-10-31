<?php

declare(strict_types=1);

/** P08 local/test read-only browsing HTTP tests with rollback-only synthetic fixtures. */
if (PHP_SAPI !== 'cli' || getenv('P08_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY') {
    fwrite(STDERR, "BLOCKED: P08_TEST_WRITE_APPROVED=YES_ROLLBACK_ONLY required.\n");
    exit(2);
}
$password = getenv('P02_TEST_DB_PASSWORD');
if (! is_string($password) || $password === '') {
    fwrite(STDERR, "BLOCKED: restricted test database password not available.\n");
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
    'P08_CART_TEST_ENABLED' => 'true', 'P08_CHECKOUT_TEST_ENABLED' => 'true',
];
unset($password);
foreach ($settings as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}
unset($settings);

try {
    require $root . '/tests/bootstrap-p03.php';
    $connection = P02TestSchemaGate::connectFromEnvironment();
    foreach (['products', 'orders', 'order_items', 'campaigns', 'customers',
        'storefront_pages', 'storefront_revisions', 'homepage_sections',
        'carts', 'cart_items', 'checkout_requests'] as $table) {
        if ((int) $connection->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() > 0) {
            throw new RuntimeException('P08 rollback fixtures require an empty test schema: ' . $table);
        }
    }
    unset($connection);
    $command = [PHP_BINARY, $phpunit,
        '--configuration=' . $root . '/phpunit.p08.xml',
        '--filter=P08GuestCartCheckoutMySqlTest', '--fail-on-empty-test-suite', '--no-progress'];
    $process = proc_open($command, [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => STDOUT, 2 => STDERR,
    ], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('P08 test process did not start.');
    }
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException('P08 suite failed with exit ' . $exit);
    }
    echo "RESULT: P08 guest cart / COD checkout MySQL suite PASS (synthetic transactions rolled back).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL P08: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
