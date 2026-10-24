<?php

declare(strict_types=1);

/**
 * Launch exactly the P03 authenticated HTTP PHPUnit tests using the isolated
 * schema guard, synthetic fixtures and DatabaseTransactions rollback.
 * No migrations, seeders, public requests or permanent test data.
 */

if (PHP_SAPI !== 'cli' || (string) getenv('P03_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY') {
    fwrite(STDERR, "BLOCKED: P03_TEST_WRITE_APPROVED=YES_ROLLBACK_ONLY required.\n");
    exit(2);
}
$password = getenv('P02_TEST_DB_PASSWORD');
if (! is_string($password) || $password === '') {
    fwrite(STDERR, "BLOCKED: Restricted test account password is missing.\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$phpUnit = $root . '/vendor/phpunit/phpunit/phpunit';
if (! is_file($phpUnit)) {
    fwrite(STDERR, "BLOCKED: PHPUnit dependency missing. Run an approved composer install from composer.lock first.\n");
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
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
];
unset($password);
foreach ($settings as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}
unset($settings);

try {
    // Preflight is read-only. Refuse to launch PHPUnit if real commerce data
    // or an unexpected schema/credential is present.
    require $root . '/tests/bootstrap-p03.php';

    $command = [PHP_BINARY, $phpUnit, '--configuration=' . $root . '/phpunit.p03.xml',
        '--filter=ManagedOrderHttpMySqlTest', '--no-progress'];
    $process = proc_open($command, [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => STDOUT,
        2 => STDERR,
    ], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the isolated PHPUnit process.');
    }
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException('HTTP regression tests failed (exit ' . $exitCode . '). Stop; do not enable P03.');
    }
    echo "RESULT: Authenticated P03 HTTP PHPUnit suite PASS (test transactions rolled back).\n";
    echo "NOTE: Browser-level CSRF and real parallel stock purchases are not validated by this suite.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL P03 HTTP: ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
