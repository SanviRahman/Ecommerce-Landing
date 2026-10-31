<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\StorefrontAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Customer-authenticated wishlist state. Never uses the admin guard or the
 * former anonymous wishlist key. Each customer has a separate session key.
 * No account can access another customer's saved items in a shared browser.
 * Persistence remains session-scoped (no database migration in this phase).
 */
final class StorefrontWishlistState
{
    private const KEY_PREFIX = 'storefront_wishlist_account_';
    private const LIMIT = 60;

    public function customer(): ?StorefrontAccount
    {
        $customer = Auth::guard('storefront')->user();

        return $customer instanceof StorefrontAccount && (bool) $customer->is_active
            ? $customer : null;
    }

    public function authenticated(): bool
    {
        return $this->customer() !== null;
    }

    /** @return list<int> */
    public function ids(Request $request): array
    {
        $customer = $this->customer();
        if ($customer === null) {
            return [];
        }

        $input = $request->session()->get(self::KEY_PREFIX.$customer->getKey(), []);
        if (! is_array($input)) {
            return [];
        }

        $ids = [];
        foreach ($input as $value) {
            if (! is_int($value) || $value < 1 || in_array($value, $ids, true)) {
                continue;
            }
            $ids[] = $value;
            if (count($ids) === self::LIMIT) {
                break;
            }
        }

        return $ids;
    }

    public function contains(int $productId): bool
    {
        return in_array($productId, $this->ids(request()), true);
    }

    public function count(): int
    {
        return count($this->ids(request()));
    }

    /** @param list<int> $ids */
    public function put(Request $request, array $ids): void
    {
        $customer = $this->customer();
        abort_unless($customer !== null, 403);
        $request->session()->put(self::KEY_PREFIX.$customer->getKey(), array_values($ids));
    }
}
