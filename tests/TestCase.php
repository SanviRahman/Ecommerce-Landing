<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * P01's test suite must never bootstrap the original or a production DB.
     * The independent phpunit.p01.xml configuration and explicit run token
     * are prerequisites. Database transactions are enabled only by the tests
     * that actually insert fixture records.
     */
    protected function setUp(): void
    {
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
                $this->markTestSkipped("P01 database safety guard: {$name} is not approved.");
            }
        }

        parent::setUp();

        $db = config('database.connections.mysql');
        if (config('database.default') !== 'mysql'
            || ($db['database'] ?? null) !== 'p01_campaign_test'
            || ($db['username'] ?? null) !== 'p01_tester'
            || ($db['host'] ?? null) !== '127.0.0.1'
            || ! empty($db['url'])) {
            $this->fail('P01 database isolation failed after application boot.');
        }
    }
}
