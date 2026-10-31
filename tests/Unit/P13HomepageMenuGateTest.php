<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/** No DB access: P13 only governs AdminLTE menu visibility, not controller writes. */
final class P13HomepageMenuGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql', array_merge(
            config('database.connections.mysql', []),
            [
                'host' => '127.0.0.1', 'port' => '3306',
                'database' => 'p01_campaign_test', 'username' => 'p01_tester',
                'url' => null, 'unix_socket' => null, 'read' => null, 'write' => null,
            ]
        ));
    }

    private function allowed(string $role = 'admin', bool $active = true): bool
    {
        $user = new User(['role' => $role, 'is_active' => $active]);

        return Gate::forUser($user)->allows('isolated-homepage-builder');
    }

    public function test_only_an_active_local_isolated_admin_sees_builder_menu(): void
    {
        self::assertTrue($this->allowed());
        self::assertFalse($this->allowed('employee'));
        self::assertFalse($this->allowed('admin', false));
    }

    public function test_original_database_and_other_accounts_never_show_builder_menu(): void
    {
        config()->set('database.connections.mysql.database', 'ecommerce_landing_db');
        self::assertFalse($this->allowed());
        config()->set('database.connections.mysql.database', 'p01_campaign_test');
        config()->set('database.connections.mysql.username', 'root');
        self::assertFalse($this->allowed());
    }

    public function test_remote_hosts_unexpected_ports_and_db_urls_are_denied(): void
    {
        config()->set('database.connections.mysql.host', 'localhost');
        self::assertFalse($this->allowed());
        config()->set('database.connections.mysql.host', '127.0.0.1');
        config()->set('database.connections.mysql.port', 3307);
        self::assertFalse($this->allowed());
        config()->set('database.connections.mysql.port', 3306);
        config()->set('database.connections.mysql.url', 'mysql://untrusted/target');
        self::assertFalse($this->allowed());
    }

    public function test_normal_admin_database_still_denies_direct_builder_url(): void
    {
        config()->set('database.connections.mysql.database', 'ecommerce_landing_db');
        $admin = new User(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/storefront/home')->assertNotFound();
    }

    public function test_sqlite_or_split_connections_never_show_builder_menu(): void
    {
        config()->set('database.default', 'sqlite');
        self::assertFalse($this->allowed());
        config()->set('database.default', 'mysql');
        config()->set('database.connections.mysql.read', ['host' => '127.0.0.1']);
        self::assertFalse($this->allowed());
    }
}
