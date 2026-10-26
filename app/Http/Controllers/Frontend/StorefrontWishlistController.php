<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Storefront\PublicCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/** Real, owner-session-scoped wishlist: no database migration or fake saved count. */
final class StorefrontWishlistController extends Controller
{
    private const SESSION_KEY = 'storefront_wishlist_ids';
    private const LIMIT = 60;

    public function index(Request $request, PublicCatalog $catalog): Response
    {
        $catalog->assertAvailable();
        $ids = $this->ids($request);
        $products = $catalog->eligibleProducts()->with(['media', 'category', 'variants.optionValues.option'])
            ->whereIn('products.id', $ids)->get()->sortBy(static function ($product) use ($ids): int {
                $index = array_search((int) $product->id, $ids, true);
                return $index === false ? PHP_INT_MAX : $index;
            })->values();

        // Inactive/removed products are never exposed just because they were saved.
        return response()->view('storefront.pages.wishlist', [
            'navigation' => $catalog->navigationCategories(),
            'products' => $products, 'catalog' => $catalog,
            'cartVersion' => $this->cartVersion($request),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function toggle(Request $request, PublicCatalog $catalog): RedirectResponse
    {
        $catalog->assertAvailable();
        $data = $request->validate(['product_id' => ['required', 'integer', 'min:1']]);
        $id = (int) $data['product_id'];
        $ids = $this->ids($request);
        $existing = array_search($id, $ids, true);
        if ($existing !== false) {
            unset($ids[$existing]);
            $message = 'Removed from wishlist.';
        } else {
            if (! $catalog->eligibleProducts()->where('products.id', $id)->exists()) {
                throw ValidationException::withMessages(['product_id' => 'Product is no longer available.']);
            }
            if (count($ids) >= self::LIMIT) {
                throw ValidationException::withMessages(['wishlist' => 'Wishlist limit reached.']);
            }
            $ids[] = $id;
            $message = 'Saved to your wishlist.';
        }
        $request->session()->put(self::SESSION_KEY, array_values($ids));
        return redirect()->back(303)->with('status', $message);
    }

    /** @return list<int> */
    private function ids(Request $request): array
    {
        $input = $request->session()->get(self::SESSION_KEY, []);
        if (! is_array($input)) {
            return [];
        }
        $ids = [];
        foreach ($input as $value) {
            if (! is_int($value) || $value < 1 || in_array($value, $ids, true)) {
                continue;
            }
            $ids[] = $value;
            if (count($ids) >= self::LIMIT) {
                break;
            }
        }
        return $ids;
    }

    private function cartVersion(Request $request): ?int
    {
        if (($request->attributes->get('p26_isolated_commerce_qa') !== true
                && $request->attributes->get('p32_local_commerce') !== true)
            || config('storefront.p08_cart_test_enabled', false) !== true) {
            return null;
        }
        $owner = app(\App\Services\Cart\GuestCartOwner::class);
        return (int) app(\App\Services\Cart\GuestCartService::class)
            ->show($owner->hash($request))['version'];
    }
}
