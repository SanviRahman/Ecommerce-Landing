<?php

declare(strict_types=1);

/** Local-only guarded P05 homepage revision integration tests; no migration or production writes. */
if (PHP_SAPI !== 'cli' || getenv('P05_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY') {
    fwrite(STDERR, "BLOCKED: P05_TEST_WRITE_APPROVED=YES_ROLLBACK_ONLY is required.\n");
    exit(2);
}
$password = getenv('P02_TEST_DB_PASSWORD');
if (! is_string($password) || $password === '') {
    fwrite(STDERR, "BLOCKED: restricted p01_tester password missing.\n");
    exit(2);
}
$root = dirname(__DIR__, 2);
$phpunit = $root . '/vendor/phpunit/phpunit/phpunit';
if (! is_file($phpunit)) {
    fwrite(STDERR, "BLOCKED: PHPUnit dependencies missing; approved composer.lock install required.\n");
    exit(2);
}
$settings = [
    'P03_ALLOW_MYSQL_TESTS' => 'yes-transaction-rollback-only',
    'P01_ALLOW_MYSQL_TESTS' => 'yes-disposable-only',
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)), 'APP_URL' => 'http://127.0.0.1',
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306',
    'DB_DATABASE' => 'p01_campaign_test', 'DB_USERNAME' => 'p01_tester',
    'DB_PASSWORD' => $password, 'DB_URL' => '', 'DB_SOCKET' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'FILESYSTEM_DISK' => 'local',
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
    foreach (['storefront_pages', 'storefront_revisions', 'homepage_sections',
        'homepage_section_categories', 'homepage_section_products', 'homepage_section_items',
        'homepage_audit_events'] as $table) {
        $count = (int) $connection->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        if ($count !== 0) {
            throw new RuntimeException('P05 rollback suite needs an empty, isolated homepage schema: ' . $table);
        }
    }
    unset($connection);
    $command = [PHP_BINARY, $phpunit,
        '--configuration=' . $root . '/phpunit.p05.xml',
        '--filter=P05HomepageMySqlTest', '--fail-on-empty-test-suite', '--no-progress'];
    $process = proc_open($command, [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => STDOUT, 2 => STDERR,
    ], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to invoke guarded P05 PHPUnit suite.');
    }
    $result = proc_close($process);
    if ($result !== 0) {
        throw new RuntimeException('P05 suite failed with exit ' . $result);
    }
    echo "RESULT: P05 homepage revision MySQL suite PASS (all transactions rolled back).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL P05: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
