<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Temporary rollout gate: P02 schemas are permitted in the disposable local
 * test database ONLY. A production migration requires a separate reviewed
 * release change and operator approval after P03/P09 validation.
 */
final class P02MigrationSafety
{
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
