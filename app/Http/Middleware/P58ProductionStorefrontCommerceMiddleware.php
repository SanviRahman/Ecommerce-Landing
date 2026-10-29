<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\P58ProductionStorefrontAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Per-request production commerce approval; no application-wide flag mutation. */
final class P58ProductionStorefrontCommerceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('p26_isolated_commerce_qa') === true
            || $request->attributes->get('p32_local_commerce') === true
            || ! app(P58ProductionStorefrontAccess::class)->allows($request)) {
            return $next($request);
        }

        $ready = app(P58ProductionStorefrontAccess::class)->checkoutReady();
        $keys = [
            'storefront.p08_cart_test_enabled' => true,
            'storefront.p08_checkout_test_enabled' => $ready,
            'storefront.p03_core_test_enabled' => $ready,
        ];
        $old = [];
        foreach ($keys as $key => $value) {
            $old[$key] = config($key);
            config()->set($key, $value);
        }
        $request->attributes->set('p58_production_commerce', true);
        $request->attributes->set('p58_production_checkout', $ready);

        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Referrer-Policy', 'same-origin');
            return $response;
        } finally {
            foreach ($old as $key => $value) {
                config()->set($key, $value);
            }
            $request->attributes->remove('p58_production_commerce');
            $request->attributes->remove('p58_production_checkout');
        }
    }
}
