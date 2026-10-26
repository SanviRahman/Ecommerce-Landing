<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Owner-initiated, loopback-only storefront functional test environment.
 * It can NEVER approve the original ecommerce_landing_db: every request
 * re-verifies the exact isolated DB and its restricted MySQL account.
 */
final class P26IsolatedCommerceQaAccess
{
    /** Pure context gate, independently regression-tested without Laravel. */
    public static function matchesContext(
        string $environment,
        bool $approved,
        string $ip,
        string $host,
        int $port
    ): bool {
        return $environment === 'local'
            && $approved
            && in_array($ip, ['127.0.0.1', '::1'], true)
            && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            && $port === 8088;
    }

    public function allows(Request $request): bool
    {
        if (! self::matchesContext(
            (string) config('app.env'),
            config('storefront.p26_isolated_commerce_qa_enabled') === true,
            (string) $request->ip(),
            (string) $request->getHost(),
            (int) $request->getPort()
        )) {
            return false;
        }

        try {
            // Verifies both config AND actual MySQL DATABASE()/CURRENT_USER().
            // Also denies an accidental switch to ecommerce_landing_db.
            P02MigrationSafety::assertIsolatedTestDatabase();
            return self::hasRestrictedGrants();
        } catch (Throwable $exception) {
            // Fail closed; never echo MySQL details to an unauthenticated user.
            return false;
        }
    }
    /** Deny global/foreign grants even when someone starts the server manually. */
    private static function hasRestrictedGrants(): bool
    {
        $allowed = [
            '`p01\\_campaign\\_test`.*', '`p01_campaign_test`.*',
            'p01\\_campaign\\_test.*', 'p01_campaign_test.*',
        ];
        $grants = DB::select('SHOW GRANTS');
        if ($grants === []) {
            return false;
        }
        foreach ($grants as $row) {
            $grant = (string) (array_values((array) $row)[0] ?? '');
            if ($grant === '' || stripos($grant, 'GRANT OPTION') !== false
                || stripos($grant, 'GRANT ALL PRIVILEGES') !== false) {
                return false;
            }
            if (preg_match('/\AGRANT\s+USAGE\s+ON\s+\*\.\*/i', $grant) === 1) {
                continue;
            }
            $on = stripos($grant, ' ON ');
            $to = $on === false ? false : stripos($grant, ' TO ', $on + 4);
            if ($to === false
                || ! in_array(trim(substr($grant, $on + 4, $to - $on - 4)), $allowed, true)) {
                return false;
            }
        }
        return true;
    }

}
