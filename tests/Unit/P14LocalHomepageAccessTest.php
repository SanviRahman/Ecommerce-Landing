<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LocalHomepageAccess;
use PHPUnit\Framework\TestCase;

/** Pure policy tests; intentionally bypass Tests\TestCase and any DB bootstrap. */
final class P14LocalHomepageAccessTest extends TestCase
{
    private function base(): array
    {
        return [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306,
            'username' => 'dev_admin', 'database' => 'ecommerce_landing_db',
            'url' => null, 'unix_socket' => null, 'read' => null, 'write' => null,
        ];
    }

    public function test_only_explicit_debug_local_mysql_is_eligible(): void
    {
        self::assertTrue(LocalHomepageAccess::matchesConfiguration('local', true, 'mysql', $this->base(), true));
        self::assertFalse(LocalHomepageAccess::matchesConfiguration('local', true, 'mysql', $this->base(), false));
        self::assertFalse(LocalHomepageAccess::matchesConfiguration('production', true, 'mysql', $this->base(), true));
        self::assertFalse(LocalHomepageAccess::matchesConfiguration('testing', true, 'mysql', $this->base(), true));
        self::assertFalse(LocalHomepageAccess::matchesConfiguration('local', false, 'mysql', $this->base(), true));
    }

    public function test_wrong_database_and_alternate_connection_are_denied(): void
    {
        foreach ([
            ['database' => 'p01_campaign_test'], ['database' => 'not_the_original_db'],
            ['driver' => 'sqlite'], ['host' => 'localhost'], ['port' => 3307],
            ['url' => 'mysql://user:pass@host/db'], ['unix_socket' => '/var/mysql.sock'],
            ['read' => ['host' => '127.0.0.1']], ['write' => ['host' => '127.0.0.1']],
        ] as $changes) {
            self::assertFalse(LocalHomepageAccess::matchesConfiguration(
                'local', true, 'mysql', array_replace($this->base(), $changes), true
            ));
        }
    }
}
