<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * P25: explicitly approved read-only BROWSER preview on the already approved
 * local Homepage Builder database. This is NOT a public release switch.
 * Only authenticated active admins may browse the six GET catalog views.
 * Cart, customer accounts, orders and all POST/PATCH/DELETE actions stay gated.
 */
final class LocalStorefrontBrowseAccess
{
    private const BROWSE_ROUTES = [
        'storefront.home', 'storefront.shop', 'storefront.category',
        'storefront.product', 'storefront.help', 'storefront.contact',
    ];

    /** Pure, testable context predicate; the actual database check is separate. */
    public static function matchesContext(
        string $environment,
        bool $debug,
        bool $approved,
        string $method,
        ?string $routeName,
        bool $activeAdmin
    ): bool {
        return $environment === 'local'
            && $debug
            && $approved
            && $method === 'GET'
            && in_array($routeName, self::BROWSE_ROUTES, true)
            && $activeAdmin;
    }

    public function allows(Request $request): bool
    {
        $admin = Auth::guard('web')->user();
        if (! self::matchesContext(
            (string) config('app.env'),
            config('app.debug') === true,
            (config('storefront.p25_local_browse_enabled') === true
                || ($request->hasSession()
                    && $request->session()->get('p26_local_storefront_preview_approved') === true)),
            strtoupper($request->method()),
            $request->route()?->getName(),
            $admin !== null && $admin->isAdmin() && $admin->is_active
        )) {
            return false;
        }

        // Uses the same strict actual MySQL account/schema identity as the P14
        // admin editor. A config toggle alone cannot authorize another database.
        try {
            LocalHomepageAccess::assertActualBuilderDatabase();
            return true;
        } catch (RuntimeException $exception) {
            return false;
        } catch (\Throwable $exception) {
            // Even a failed local MySQL connection must not expose the catalog.
            return false;
        }
    }
}
