<?php

/**
 * Unlike plain phpunit.xml, this suite cannot bootstrap without an explicitly
 * identified disposable MySQL account/database. No database/URL credentials
 * are included in this source ZIP. Never copy a production .env into it.
 */
$expected = [
    'P01_ALLOW_MYSQL_TESTS' => 'yes-disposable-only',
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_DATABASE' => 'p01_campaign_test',
    'DB_USERNAME' => 'p01_tester',
];

foreach ($expected as $name => $value) {
    if ((string) getenv($name) !== $value) {
        throw new RuntimeException("P01 test bootstrap stopped: {$name} must match the isolated test environment.");
    }
}

if (! is_file(__DIR__ . '/../vendor/autoload.php')) {
    throw new RuntimeException('P01 tests require locked Composer dev dependencies.');
}

require __DIR__ . '/../vendor/autoload.php';
