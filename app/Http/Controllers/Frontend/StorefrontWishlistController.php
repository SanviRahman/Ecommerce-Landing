<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Storefront\PublicCatalog;
use App\Support\StorefrontWishlistState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/** Only active, authenticated storefront customers may see or change a wishlist. */
final class StorefrontWishlistController extends Controller
{
    private const LIMIT = 60;

    public function index(Request $request, PublicCatalog $catalog, StorefrontWishlistState $wishlist): Response|RedirectResponse
    {
        if (! $wishlist->authenticated()) {
            return $this->requireCustomerLogin();
        }
        $catalog->assertAvailable();
        $ids = $wishlist->ids($request);
        $products = $catalog->eligibleProducts()->with(['media', 'category', 'variants.optionValues.option'])
            ->whereIn('products.id', $ids)->get()->sortBy(static function ($product) use ($ids): int {
                $index = array_search((int) $product->id, $ids, true);
                return $index === false ? PHP_INT_MAX : $index;
            })->values();

        return response()->view('storefront.pages.wishlist', [
            'navigation' => $catalog->navigationCategories(),
            'products' => $products, 'catalog' => $catalog,
            'cartVersion' => $this->cartVersion($request),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function toggle(Request $request, PublicCatalog $catalog, StorefrontWishlistState $wishlist): RedirectResponse
    {
        // Direct POSTs, forged anonymous forms and inactive accounts all fail closed.
        // This check must be server-side, not just hidden links in Blade.
        if (! $wishlist->authenticated()) {
            return $this->requireCustomerLogin();
        }
        $catalog->assertAvailable();
        $data = $request->validate(['product_id' => ['required', 'integer', 'min:1']]);
        $id = (int) $data['product_id'];
        $ids = $wishlist->ids($request);
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
        $wishlist->put($request, array_values($ids));

        return redirect()->back(303)->with('status', $message);
    }

    private function requireCustomerLogin(): RedirectResponse
    {
        return redirect()->route('storefront.account.login')
            ->with('status', 'Please log in or create a customer account to save products to your wishlist.');
    }

    private function cartVersion(Request $request): ?int
    {
        if (($request->attributes->get('p26_isolated_commerce_qa') !== true
                && $request->attributes->get('p32_local_commerce') !== true
                && $request->attributes->get('p58_production_commerce') !== true)
            || config('storefront.p08_cart_test_enabled', false) !== true) {
            return null;
        }
        $owner = app(\App\Services\Cart\GuestCartOwner::class);
        return (int) app(\App\Services\Cart\GuestCartService::class)
            ->show($owner->hash($request))['version'];
    }
}
