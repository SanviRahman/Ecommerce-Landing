<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ShippingCharge;
use App\Services\Cart\GuestCartOwner;
use App\Services\Cart\GuestCartService;
use App\Services\Checkout\StorefrontCheckout;
use App\Services\Storefront\PublicCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use DomainException;
use InvalidArgumentException;
use OverflowException;

/** Public guest-only cart and COD form, restricted to the isolated P02 test DB. */
final class StorefrontCartController extends Controller
{
    public function index(Request $request, GuestCartOwner $owner, GuestCartService $cart, PublicCatalog $catalog): Response
    {
        $cart->assertEnabled();
        return $this->noStore(response()->view('storefront.pages.cart', [
            'cart' => $cart->show($owner->hash($request)),
            'navigation' => $catalog->navigationCategories(),
        ]));
    }

    public function add(Request $request, GuestCartOwner $owner, GuestCartService $cart): RedirectResponse
    {
        $cart->assertEnabled();
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'cart_version' => ['required', 'integer', 'min:0'],
            'intent' => ['nullable', 'in:cart,checkout'],
        ]);
        $cart->add($owner->hash($request), (int) $data['product_variant_id'],
            (int) $data['quantity'], (int) $data['cart_version']);
        if (($data['intent'] ?? 'cart') === 'checkout'
            && config('storefront.p08_checkout_test_enabled', false) === true
            && config('storefront.p03_core_test_enabled', false) === true) {
            return redirect()->route('storefront.checkout');
        }
        return redirect()->route('storefront.cart')->with('status', 'Added to your cart.');
    }

    public function change(
        Request $request, int $item, GuestCartOwner $owner, GuestCartService $cart
    ): RedirectResponse {
        $cart->assertEnabled();
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:1000'],
            'cart_version' => ['required', 'integer', 'min:1'],
        ]);
        $cart->change($owner->hash($request), $item, (int) $data['quantity'], (int) $data['cart_version']);
        return redirect()->route('storefront.cart')->with('status', 'Cart updated.');
    }

    public function checkout(
        Request $request, GuestCartOwner $owner, GuestCartService $cart,
        StorefrontCheckout $checkout, PublicCatalog $catalog
    ): Response {
        $checkout->assertEnabled();
        $summary = $cart->show($owner->hash($request));
        if (! $summary['can_checkout']) {
            abort(409, 'Please review your cart before checkout.');
        }
        return $this->noStore(response()->view('storefront.pages.checkout', [
            'cart' => $summary, 'areas' => ShippingCharge::resolvedForCampaign(null),
            'checkoutKey' => (string) Str::uuid(),
            'navigation' => $catalog->navigationCategories(),
        ]));
    }

    public function place(
        Request $request, GuestCartOwner $owner, StorefrontCheckout $checkout
    ): RedirectResponse {
        $checkout->assertEnabled();
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01[0-9]{9}$/'],
            'address' => ['required', 'string', 'min:8', 'max:2000'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'shipping_area_id' => ['required', 'integer', 'min:1'],
            'cart_version' => ['required', 'integer', 'min:1'],
            'checkout_key' => ['required', 'uuid'],
        ]);
        try {
            $successToken = $checkout->place($request, $owner->hash($request), $data);
        } catch (DomainException $exception) {
            if (str_contains($exception->getMessage(), 'Checkout key was reused')) {
                abort(409, 'A checkout key cannot be reused for different order details.');
            }
            throw ValidationException::withMessages([
                'cart' => 'Checkout could not be completed. Review current stock and prices, then retry.',
            ]);
        } catch (InvalidArgumentException|OverflowException $exception) {
            throw ValidationException::withMessages([
                'cart' => 'Checkout details or totals are invalid. Please review your cart.',
            ]);
        }
        return redirect()->route('order.success', $successToken)
            ->with('success', 'Your order has been received.');
    }

    private function noStore(Response $response): Response
    {
        return $response->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
