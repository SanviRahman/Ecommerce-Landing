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
 * Owner-requested local storefront commerce for the EXISTING main database.
 * Never a production release switch. No admin session, QA server or .env edit.
 * All transactional services verify this exact MySQL identity independently.
 */
final class P32LocalStorefrontAccess
{
    private const LOCAL_SCHEMA = 'ecommerce_landing_db';

    public static function matchesContext(string $env, bool $debug, string $ip, string $host, int $port): bool
    {
        return $env === 'local' && $debug
            && in_array($ip, ['127.0.0.1', '::1'], true)
            && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            && $port === 8000;
    }

    public function allows(Request $request): bool
    {
        if (! self::matchesContext(
            (string) config('app.env'),
            config('app.debug') === true,
            (string) $request->ip(),
            (string) $request->getHost(),
            (int) $request->getPort()
        )) {
            return false;
        }

        try {
            $this->assertActualMainDatabase($request);
            foreach ([
                'storefront_accounts', 'carts', 'cart_items', 'checkout_requests',
                'inventory_movements', 'product_variants', 'product_variant_values',
                'products', 'categories', 'orders', 'order_items', 'shipping_charges',
                'storefront_pages', 'storefront_revisions',
            ] as $table) {
                if (! Schema::hasTable($table)) {
                    return false;
                }
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** Admin orders may finalize storefront holds only on the same verified local main DB. */
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
            $this->assertActualMainDatabase($request);
            return Schema::hasColumn('orders', 'storefront_stock_state');
        } catch (Throwable) {
            return false;
        }
    }

    public function assertAdminManaged(): void
    {
        if (! $this->allowsAdminManaged()) {
            throw new RuntimeException('Managed order operation is not authorized on this local database.');
        }
    }

    public function assertActualMainDatabase(?Request $request = null): void
    {
        $request ??= request();
        if (! self::matchesContext(
            (string) config('app.env'), config('app.debug') === true,
            (string) $request->ip(), (string) $request->getHost(), (int) $request->getPort()
        ) || config('database.default') !== 'mysql') {
            throw new RuntimeException('Local storefront commerce is unavailable outside the approved loopback development server.');
        }

        $connection = DB::connection();
        $settings = $connection->getConfig();
        $username = $settings['username'] ?? null;
        if (($settings['driver'] ?? null) !== 'mysql'
            || ($settings['database'] ?? null) !== self::LOCAL_SCHEMA
            || ! is_string($username) || trim($username) === ''
            || ($settings['host'] ?? null) !== '127.0.0.1'
            || (int) ($settings['port'] ?? 0) !== 3306
            || ! empty($settings['url']) || ! empty($settings['unix_socket'])
            || ! empty($settings['read']) || ! empty($settings['write'])) {
            throw new RuntimeException('Local storefront MySQL configuration is not the expected main database.');
        }

        $actual = $connection->selectOne('SELECT DATABASE() AS db_name, CURRENT_USER() AS db_account');
        if (! $actual || $actual->db_name !== self::LOCAL_SCHEMA
            || ! str_starts_with((string) $actual->db_account, $username . '@')) {
            throw new RuntimeException('Local storefront MySQL identity verification failed.');
        }
    }

    /**
     * Read-only release check. Existing legacy campaign products can stay
     * unreconciled; new storefront checkout only accepts inventory_version=1
     * products and revalidates every row under the order transaction.
     */
    public function checkoutReady(): bool
    {
        try {
            // Browse/cart remain available before P61 migration; CHECKOUT is
            // disabled rather than silently falling back to immediate deduction.
            if (! Schema::hasColumn('orders', 'storefront_stock_state')) {
                return false;
            }
            // No global checkout unless basic stock and shipping are coherent.
            if (! DB::table('shipping_charges')->whereNull('campaign_id')
                ->where('status', 1)->whereNull('deleted_at')->exists()) {
                return false;
            }
            if (! DB::table('storefront_pages as p')
                ->join('storefront_revisions as r', 'r.id', '=', 'p.published_revision_id')
                ->where('p.slug', 'home')->where('p.mode', 'storefront')
                ->where('r.status', 'published')
                ->whereColumn('r.storefront_page_id', 'p.id')->exists()) {
                return false;
            }
            if (! DB::table('products as p')->join('categories as c', 'c.id', '=', 'p.category_id')
                ->where('p.status', 1)->whereNull('p.deleted_at')
                ->where('p.inventory_version', 1)->where('c.status', 1)
                ->whereNull('c.deleted_at')->exists()) {
                return false;
            }
            if (DB::table('products as p')->where('p.inventory_version', 1)
                ->whereRaw('COALESCE(p.stock,0) <> COALESCE((SELECT SUM(v.stock) FROM product_variants v WHERE v.product_id=p.id AND v.deleted_at IS NULL),0)')
                ->exists()) {
                return false;
            }
            if (DB::table('products as p')->where('p.inventory_version', 1)
                ->where('p.has_variants', 0)->whereNull('p.deleted_at')
                ->whereRaw("((SELECT COUNT(*) FROM product_variants v WHERE v.product_id=p.id AND v.is_default=1 AND v.is_active=1 AND v.option_signature='default' AND v.deleted_at IS NULL) <> 1 OR (SELECT COUNT(*) FROM product_variants v WHERE v.product_id=p.id AND v.is_default=1) <> 1)")
                ->exists()) {
                return false;
            }
            // Optioned products need assigned, active, complete variant combinations.
            if (DB::table('products as p')->where('p.status', 1)
                ->whereNull('p.deleted_at')->where('p.has_variants', 1)
                ->whereRaw("(NOT EXISTS (SELECT 1 FROM product_option_assignments a WHERE a.product_id=p.id) OR NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id=p.id AND v.is_active=1 AND v.is_default=0 AND v.deleted_at IS NULL AND (SELECT COUNT(*) FROM product_variant_values vv WHERE vv.product_variant_id=v.id)=(SELECT COUNT(*) FROM product_option_assignments a WHERE a.product_id=p.id) AND NOT EXISTS (SELECT 1 FROM product_variant_values vv JOIN product_option_values ov ON ov.id=vv.product_option_value_id JOIN product_options o ON o.id=ov.product_option_id WHERE vv.product_variant_id=v.id AND (ov.is_active <> 1 OR o.is_active <> 1 OR NOT EXISTS (SELECT 1 FROM product_option_assignments a WHERE a.product_id=p.id AND a.product_option_id=vv.product_option_id)))))")
                ->exists()) {
                return false;
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function assertStorefrontCheckout(): void
    {
        $request = request();
        if ($request->attributes->get('p32_local_checkout') !== true
            || ! $request->routeIs('storefront.checkout', 'storefront.checkout.place')) {
            throw new RuntimeException('Local storefront checkout is not approved in this request.');
        }
        $this->assertActualMainDatabase($request);
        if (! $this->checkoutReady()) {
            throw new RuntimeException('Local storefront checkout data is not ready (stock or shipping).');
        }
    }
}
