<?php

declare(strict_types=1);

/**
 * Execute only the two existing P01 security/characterization test classes.
 * The caller must deliberately authorize rollback-only writes to the isolated
 * `p01_campaign_test` schema. No .env password, local or production app key is used.
 * Any existing commerce rows cause an immediate fail-closed refusal.
 */

if (PHP_SAPI !== 'cli' || getenv('P01_TEST_WRITE_APPROVED') !== 'YES_ROLLBACK_ONLY') {
    fwrite(STDERR, "BLOCKED: P01_TEST_WRITE_APPROVED=YES_ROLLBACK_ONLY required.\n");
    exit(2);
}
$password = getenv('P02_TEST_DB_PASSWORD');
if (! is_string($password) || $password === '') {
    fwrite(STDERR, "BLOCKED: Restricted test database password must be supplied via P02_TEST_DB_PASSWORD.\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
$phpUnit = $root . '/vendor/phpunit/phpunit/phpunit';
if (! is_file($phpUnit)) {
    fwrite(STDERR, "BLOCKED: Locked development dependencies (PHPUnit) are missing.\n");
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
foreach ($settings as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
unset($settings);

$reportFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
    . 'p01-suite-' . bin2hex(random_bytes(12)) . '.xml';

try {
    // Independent PDO preflight verifies credentials, P02 schema and zero
    // preexisting products, orders, order items, customers and campaigns.
    require $root . '/tests/bootstrap-p03.php';

    $command = [
        PHP_BINARY,
        $phpUnit,
        '--configuration=' . $root . '/phpunit.p01.xml',
        '--testsuite=P01',
        '--no-progress',
        '--log-junit=' . $reportFile,
    ];
    $process = proc_open($command, [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => STDOUT,
        2 => STDERR,
    ], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start guarded P01 PHPUnit suite.');
    }
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException("P01 characterization suite failed (exit $exitCode); no production action is allowed.");
    }
    $xml = new DOMDocument();
    if (! is_file($reportFile) || ! $xml->load($reportFile, LIBXML_NONET)) {
        throw new RuntimeException('JUnit test report unavailable; refusing to claim a passing P01 suite.');
    }
    $count = $xml->getElementsByTagName('testcase')->length;
    if ($count !== 6) {
        throw new RuntimeException("P01 suite ran $count tests; exactly 6 are required to accept this gate.");
    }
    echo "RESULT: 6/6 P01 PHPUNIT SECURITY/CHARACTERIZATION TESTS PASS (transactions rolled back).\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL P01: ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if (is_file($reportFile)) {
        @unlink($reportFile);
    }
}
