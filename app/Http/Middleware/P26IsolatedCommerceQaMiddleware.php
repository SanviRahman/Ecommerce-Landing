<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\P26IsolatedCommerceQaAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-request P26 isolated QA bridge only. Baseline P03/P07/P08 config stays
 * disabled. Real checkout/stock/ownership code still performs independent
 * P02 restricted-MySQL identity checks on every relevant operation.
 */
final class P26IsolatedCommerceQaMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(P26IsolatedCommerceQaAccess::class)->allows($request)) {
            // The P26 tester is optional. Normal visitors can browse published
            // catalog pages without admin login or a readiness/consent redirect.
            // Independent cart/checkout guards still reject main-DB writes.
            return $next($request);
        }

        $keys = [
            'storefront.p07_catalog_test_enabled',
            'storefront.p08_cart_test_enabled',
            'storefront.p08_checkout_test_enabled',
            'storefront.p03_core_test_enabled',
        ];
        $old = [];
        foreach ($keys as $key) {
            $old[$key] = config($key);
            config()->set($key, true);
        }
        $request->attributes->set('p26_isolated_commerce_qa', true);

        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Referrer-Policy', 'same-origin');
            return $response;
        } finally {
            // Do not leak request-scoped feature switches to a reused worker.
            config()->set($old);
            $request->attributes->remove('p26_isolated_commerce_qa');
        }
    }
}
