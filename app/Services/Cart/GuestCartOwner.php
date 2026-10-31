<?php

declare(strict_types=1);

namespace App\Services\Cart;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/** Cookie is encrypted by Laravel's existing EncryptCookies middleware. */
final class GuestCartOwner
{
    private const COOKIE = 'storefront_guest_owner_v1';
    private const QA_COOKIE = 'p26_storefront_guest_owner_v1';
    private const MINUTES = 43200;

    public function hash(Request $request): string
    {
        // Different cookie for the isolated 8088 QA storefront so it cannot
        // overwrite a future real storefront owner token on localhost:8000.
        $cookieName = $request->attributes->get('p26_isolated_commerce_qa', false) === true
            ? self::QA_COOKIE : self::COOKIE;
        $key = 'storefront_guest_owner_token';
        $token = $request->attributes->get($key);
        if (! is_string($token)) {
            $candidate = $request->cookie($cookieName);
            if (is_string($candidate) && preg_match('/\A[a-f0-9]{64}\z/D', $candidate)) {
                $token = $candidate;
            } else {
                $token = bin2hex(random_bytes(32));
                Cookie::queue(cookie(
                    $cookieName, $token, self::MINUTES, '/', null,
                    $request->isSecure(), true, false, 'lax'
                ));
            }
            $request->attributes->set($key, $token);
        }

        // No client-provided cart IDs, owner hashes or actor IDs are accepted.
        return hash('sha256', $token);
    }
}
