<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\P32LocalStorefrontAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Local main-DB storefront only. P26 isolated commerce is never modified. */
final class P32LocalStorefrontCommerceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('p26_isolated_commerce_qa') === true
            || ! app(P32LocalStorefrontAccess::class)->allows($request)) {
            return $next($request);
        }

        // Local customers do not need a global shipping/homepage/catalog readiness
        // review before buying. The schema must exist; each product, price and stock
        // is revalidated by the cart/checkout transaction itself.
        $checkoutSchemaReady = app(P32LocalStorefrontAccess::class)->checkoutReady();
        $keys = [
            'storefront.p08_cart_test_enabled' => true,
            'storefront.p08_checkout_test_enabled' => $checkoutSchemaReady,
            'storefront.p03_core_test_enabled' => $checkoutSchemaReady,
        ];
        $old = [];
        foreach ($keys as $key => $value) {
            $old[$key] = config($key);
            config()->set($key, $value);
        }
        $request->attributes->set('p32_local_commerce', true);
        $request->attributes->set('p32_local_checkout', $checkoutSchemaReady);
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Referrer-Policy', 'same-origin');
            return $response;
        } finally {
            foreach ($old as $key => $value) {
                config()->set($key, $value);
            }
            $request->attributes->remove('p32_local_commerce');
            $request->attributes->remove('p32_local_checkout');
        }
    }
}
