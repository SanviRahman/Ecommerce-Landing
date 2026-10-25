<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * P14: explicit LOCAL-DEVELOPMENT permission for the existing homepage editor.
 * Not a release switch: never enables inventory, cart, checkout, or public shop.
 * The default is deny, and the live connection identity is rechecked by the service.
 */
final class LocalHomepageAccess
{
    public const DATABASE = 'ecommerce_landing_db';
    public const MIGRATION_CONSENT = 'YES_LOCAL_P02_SCHEMA_ONLY';

    /** Pure predicate, unit-testable without Laravel or database credentials. */
    public static function matchesConfiguration(
        string $environment,
        bool $debug,
        string $defaultConnection,
        array $db,
        bool $explicitApproval
    ): bool {
        $username = $db['username'] ?? null;

        return $explicitApproval
            && $environment === 'local'
            && $debug === true
            && $defaultConnection === 'mysql'
            && ($db['driver'] ?? null) === 'mysql'
            && ($db['database'] ?? null) === self::DATABASE
            && is_string($username) && trim($username) !== ''
            && ($db['host'] ?? null) === '127.0.0.1'
            && (int) ($db['port'] ?? 0) === 3306
            && empty($db['url'])
            && empty($db['unix_socket'])
            && empty($db['read'])
            && empty($db['write']);
    }

    /** Config preflight ONLY: safe for the AdminLTE menu before migrations run. */
    public static function configuredForBuilder(): bool
    {
        return self::matchesConfiguration(
            (string) config('app.env'),
            config('app.debug') === true,
            (string) config('database.default'),
            (array) config('database.connections.mysql', []),
            config('storefront.p14_local_homepage_builder_enabled') === true
        );
    }

    /** Migration permission is independent of builder visibility; never implicit. */
    public static function configuredForMigrations(): bool
    {
        return app()->runningInConsole()
            && self::matchesConfiguration(
                (string) config('app.env'),
                config('app.debug') === true,
                (string) config('database.default'),
                (array) config('database.connections.mysql', []),
                config('storefront.p14_local_p02_migration_approval') === self::MIGRATION_CONSENT
            );
    }

    public static function assertActualBuilderDatabase(): void
    {
        self::assertActualLocalDatabase(false);
    }

    public static function assertActualMigrationDatabase(): void
    {
        self::assertActualLocalDatabase(true);
    }

    /**
     * Checks the ACTUAL database connection, not merely the .env/menu setting.
     * No mutations and no output of credentials or query results.
     */
    private static function assertActualLocalDatabase(bool $migration): void
    {
        $allowed = $migration ? self::configuredForMigrations() : self::configuredForBuilder();
        if (! $allowed) {
            throw new RuntimeException('P14 local homepage permission is disabled or database configuration is unsafe.');
        }

        $connection = DB::connection();
        $settings = $connection->getConfig();
        if (! self::matchesConfiguration(
            (string) config('app.env'),
            config('app.debug') === true,
            (string) config('database.default'),
            $settings,
            $allowed
        )) {
            throw new RuntimeException('P14 effective MySQL connection does not match the approved local database.');
        }

        $identity = $connection->selectOne('SELECT DATABASE() AS schema_name, CURRENT_USER() AS account');
        if ($identity === null
            || $identity->schema_name !== self::DATABASE
            || ! str_starts_with((string) $identity->account, (string) $settings['username'] . '@')) {
            throw new RuntimeException('P14 actual MySQL database/account identity verification failed.');
        }
    }
}
