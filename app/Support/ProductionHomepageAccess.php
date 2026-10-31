<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Production homepage editing is independent of production checkout approval.
 * Editing still requires authenticated, active admin + CSRF + the actual MySQL
 * connection on the configured HTTPS production host. Never use P14/local flags.
 */
final class ProductionHomepageAccess
{
    public static function configuredForBuilder(?Request $request = null): bool
    {
        $request ??= request();
        $appUrl = (string) config('app.url', '');
        $host = parse_url($appUrl, PHP_URL_HOST);
        $db = config('database.connections.mysql', []);

        return config('app.env') === 'production'
            && config('app.debug') === false
            && parse_url($appUrl, PHP_URL_SCHEME) === 'https'
            && is_string($host) && $host !== ''
            && strtolower($request->getHost()) === strtolower($host)
            && config('database.default') === 'mysql'
            && is_array($db)
            && ($db['driver'] ?? null) === 'mysql'
            && is_string($db['database'] ?? null)
            && preg_match('/\A[a-zA-Z0-9_]{1,64}\z/D', $db['database']) === 1
            && empty($db['read']) && empty($db['write']);
    }

    public static function assertActualBuilderDatabase(): void
    {
        if (! self::configuredForBuilder()) {
            throw new RuntimeException('The HTTPS production homepage editor is unavailable on this host.');
        }

        $expected = config('database.connections.mysql.database');
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== $expected) {
            throw new RuntimeException('The production homepage editor has a mismatched database connection.');
        }
        $actual = $connection->selectOne('SELECT DATABASE() AS schema_name');
        if ($actual === null || $actual->schema_name !== $expected) {
            throw new RuntimeException('The production homepage database identity could not be verified.');
        }
    }
}
