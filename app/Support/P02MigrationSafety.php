<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanent P02 restricted test database guard used by catalog, stock, cart,
 * checkout, migration DOWN and rollback-only QA. P14 adds a SEPARATE, explicit
 * local-debug-only forward migration path; it never loosens this method or
 * permits a production/staging schema upgrade.
 */
final class P02MigrationSafety
{
    /**
     * P14 forward-only LOCAL schema upgrade. Never authorizes a rollback,
     * an arbitrary database, or any other isolated-only catalog/stock service.
     */
    public static function assertApprovedP02Up(): void
    {
        if (LocalHomepageAccess::configuredForMigrations()) {
            LocalHomepageAccess::assertActualMigrationDatabase();
            return;
        }

        self::assertIsolatedTestDatabase();
    }

    public static function assertIsolatedTestDatabase(): void
    {
        if (! app()->environment(['local', 'testing']) || config('database.default') !== 'mysql') {
            throw new RuntimeException('P02 migration blocked outside the approved local MySQL test environment.');
        }

        $connection = DB::connection();
        $settings = $connection->getConfig();

        if (($settings['database'] ?? null) !== 'p01_campaign_test'
            || ($settings['username'] ?? null) !== 'p01_tester'
            || ($settings['host'] ?? null) !== '127.0.0.1'
            || (int) ($settings['port'] ?? 0) !== 3306
            || ! empty($settings['url'])
            || ! empty($settings['unix_socket'])
            || ! empty($settings['read'])
            || ! empty($settings['write'])) {
            throw new RuntimeException('P02 migration blocked: database config does not identify the isolated p01_campaign_test account.');
        }

        $identity = $connection->selectOne('SELECT DATABASE() AS schema_name, CURRENT_USER() AS account');
        if ($identity === null
            || $identity->schema_name !== 'p01_campaign_test'
            || ! str_starts_with((string) $identity->account, 'p01_tester@')) {
            throw new RuntimeException('P02 migration blocked: actual MySQL connection identity failed verification.');
        }
    }
}
