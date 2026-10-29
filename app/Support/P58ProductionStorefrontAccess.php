<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * P58 explicit production storefront release. A boolean flag alone is NEVER
 * enough: verify the canonical HTTPS host and the actual MySQL database.
 * No authorization is inherited from a user-supplied request field.
 */
final class P58ProductionStorefrontAccess
{
    public static function matchesContext(
        string $environment,
        bool $debug,
        bool $enabled,
        string $requestHost,
        string $expectedHost,
        string $appUrl
    ): bool {
        $expectedHost = strtolower(trim($expectedHost));
        $requestHost = strtolower(trim($requestHost));
        $urlHost = parse_url($appUrl, PHP_URL_HOST);
        $scheme = parse_url($appUrl, PHP_URL_SCHEME);

        return $environment === 'production'
            && ! $debug
            && $enabled
            && $expectedHost !== ''
            && filter_var($expectedHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
            && $requestHost === $expectedHost
            && is_string($urlHost) && strtolower($urlHost) === $expectedHost
            && $scheme === 'https';
    }

    /** A production request must match both configured and actual DB identity. */
    public function assertApprovedDatabase(?Request $request = null, bool $requireCommerceEnabled = true): void
    {
        $request ??= request();
        if (! self::matchesContext(
            (string) config('app.env'),
            config('app.debug') === true,
            ! $requireCommerceEnabled || config('storefront.p58_production_commerce_enabled', false) === true,
            (string) $request->getHost(),
            (string) config('storefront.p58_production_host', ''),
            (string) config('app.url')
        ) || config('database.default') !== 'mysql') {
            throw new RuntimeException('Production commerce has not been approved for this environment.');
        }

        $expectedDb = trim((string) config('storefront.p58_production_database', ''));
        $settings = DB::connection()->getConfig();
        if ($expectedDb === '' || strlen($expectedDb) > 64
            || ! preg_match('/\A[a-zA-Z0-9_]+\z/D', $expectedDb)
            || ($settings['driver'] ?? null) !== 'mysql'
            || ($settings['database'] ?? null) !== $expectedDb
            || ! empty($settings['read']) || ! empty($settings['write'])) {
            throw new RuntimeException('Production commerce database configuration is not approved.');
        }

        $database = DB::selectOne('SELECT DATABASE() AS schema_name');
        if ($database === null || (string) $database->schema_name !== $expectedDb) {
            throw new RuntimeException('Production commerce database identity could not be verified.');
        }
    }

    public function allows(Request $request): bool
    {
        try {
            $this->assertApprovedDatabase($request);
            foreach ([
                'storefront_pages', 'storefront_revisions', 'storefront_accounts',
                'carts', 'cart_items', 'checkout_requests', 'inventory_movements',
                'product_variants', 'product_variant_values', 'product_option_assignments',
                'products', 'categories', 'orders', 'order_items', 'shipping_charges',
            ] as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }
            return true;
        } catch (Throwable) {
            // Fail closed without leaking the hosting account/DB name to a customer.
            return false;
        }
    }

    /** Same strict read-only stock/shipping/published-home checks as local P32. */
    public function checkoutReady(): bool
    {
        return app(P32LocalStorefrontAccess::class)->checkoutReady();
    }

    /** Independent verification inside every order/stock/idempotency writer. */
    public function assertStorefrontCheckout(): void
    {
        $request = request();
        if ($request->attributes->get('p58_production_checkout') !== true
            || ! $request->routeIs('storefront.checkout', 'storefront.checkout.place')) {
            throw new RuntimeException('Production checkout request context is not authorized.');
        }
        $this->assertApprovedDatabase($request);
        if (! $this->checkoutReady()) {
            throw new RuntimeException('Production checkout requires reconciled stock and active global shipping.');
        }
    }

    /** Admin managed-order transitions use the existing auth/role/CSRF routes. */
    public function allowsAdminManaged(?Request $request = null): bool
    {
        $request ??= request();
        if (! $request->routeIs('admin.orders.*')) {
            return false;
        }
        $user = Auth::user();
        if (! $user || ! (bool) $user->is_active
            || ! in_array((string) $user->role, ['admin', 'employee'], true)) {
            return false;
        }
        try {
            // An emergency storefront sales pause must NEVER bypass existing
            // managed-order inventory protection for admin status changes.
            $this->assertApprovedDatabase($request, false);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function assertAdminManaged(): void
    {
        if (! $this->allowsAdminManaged()) {
            throw new RuntimeException('Managed-order operation is not authorized on this deployment.');
        }
    }
}
