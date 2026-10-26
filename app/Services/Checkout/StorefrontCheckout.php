<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ShippingCharge;
use App\Services\Cart\GuestCartService;
use App\Services\CustomerIdentityService;
use App\Services\Inventory\InventoryAllocator;
use App\Services\OrderAssignmentService;
use App\Services\OrderBlockService;
use App\Support\ExactBdt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use DomainException;

/** Single-transaction, server-quoted, COD-only storefront checkout (isolated-test-only). */
final class StorefrontCheckout
{
    public function __construct(
        private readonly GuestCartService $carts,
        private readonly CheckoutIdempotencyService $idempotency,
        private readonly InventoryAllocator $inventory,
        private readonly CustomerIdentityService $identities,
        private readonly OrderBlockService $blocked,
        private readonly OrderAssignmentService $assignment,
    ) {}

    public function assertEnabled(): void
    {
        $this->carts->assertEnabled();
        abort_unless(config('storefront.p08_checkout_test_enabled', false) === true
            && config('storefront.p03_core_test_enabled', false) === true, 404);
        if (request()->attributes->get('p32_local_commerce', false) === true) {
            app(\App\Support\P32LocalStorefrontAccess::class)->assertStorefrontCheckout();
        }
    }

    /** @param array<string,mixed> $data */
    public function place(Request $request, string $ownerHash, array $data): string
    {
        $this->assertEnabled();
        $name = trim(preg_replace('/\s+/u', ' ', (string) $data['customer_name']));
        $phone = $this->identities->normalizePhone((string) $data['phone']);
        $address = trim((string) $data['address']);
        $note = trim((string) ($data['customer_note'] ?? ''));
        $expected = (int) $data['cart_version'];
        $shippingId = (int) $data['shipping_area_id'];
        $key = (string) $data['checkout_key'];
        if ($this->blocked->findActiveBlock($phone, $request->ip())) {
            throw ValidationException::withMessages(['phone' => 'Checkout is unavailable for this contact.']);
        }
        return DB::transaction(function () use (
            $request, $ownerHash, $expected, $shippingId, $key,
            $name, $phone, $address, $note
        ): string {
            // Serialize ALL checkout attempts for this owned cart before claiming an
            // idempotency key. Otherwise two transactions can each observe a missing
            // claim and race to insert the same unique (owner_hash, key), producing
            // an avoidable SQL duplicate/deadlock instead of a stable replay.
            // Do NOT validate version yet: successful checkout increments the cart
            // version, and a replay must still return the original order token.
            $cartIdentity = DB::table('carts')
                ->where('owner_hash', $ownerHash)->lockForUpdate()->first(['id']);
            if ($cartIdentity === null) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart no longer exists. Refresh and start a new checkout.',
                ]);
            }
            // Hash the authoritative, locked cart identity, not a pre-transaction
            // snapshot. It remains identical after order completion clears lines.
            $requestHash = hash('sha256', json_encode([
                'channel' => 'storefront', 'cart_id' => (string) $cartIdentity->id,
                'version' => $expected, 'shipping_area_id' => $shippingId,
                'name' => $name, 'phone' => $phone, 'address' => $address, 'note' => $note,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $claim = $this->idempotency->claim($ownerHash, $key, $requestHash, 'storefront');
            if ($claim['state'] === 'replayed') {
                $completed = Order::query()->whereKey($claim['order_id'])->where('inventory_version', 1)->first();
                if (! $completed) {
                    throw new DomainException('Saved checkout no longer has an authorized order.');
                }
                return (string) $completed->success_token;
            }
            $cart = $this->carts->assertOwnedCart($ownerHash, $expected);
            $lines = DB::table('cart_items')->where('cart_id', $cart->id)
                ->orderBy('product_variant_id')->lockForUpdate()->get();
            if ($lines->isEmpty() || $lines->count() > GuestCartService::MAX_LINES) {
                throw ValidationException::withMessages(['cart' => 'Please add available products before checkout.']);
            }
            $subTotal = 0;
            $freeDelivery = false;
            $checked = [];
            foreach ($lines as $line) {
                $variant = ProductVariant::with(['product.category', 'optionValues.option'])->find($line->product_variant_id);
                $product = $variant?->product;
                $qty = (int) $line->quantity;
                if (! $product || ! $this->carts->isPurchasable($product, $variant)
                    || $qty < 1 || $qty > GuestCartService::MAX_QUANTITY
                    || $qty > (int) $variant->stock || (int) $line->product_id !== (int) $product->id) {
                    throw ValidationException::withMessages(['cart' => 'A cart item has changed or is out of stock.']);
                }
                $unit = $this->carts->price($product, $variant);
                if ($unit < 1 || ExactBdt::fromDecimal((string) $line->quoted_unit_price) !== $unit) {
                    throw ValidationException::withMessages([
                        'cart' => 'A price changed. Update the cart line to accept the new quote first.',
                    ]);
                }
                $lineTotal = ExactBdt::multiply($unit, $qty);
                $subTotal = ExactBdt::add($subTotal, $lineTotal);
                $freeDelivery = $freeDelivery || (bool) $product->is_free_delivery;
                $checked[] = [$product, $variant, $qty, $unit, $lineTotal];
            }
            // Shipping area must be configured and active, even for free-delivery orders.
            $shipping = ShippingCharge::resolvedForCampaign(null)->first(
                static fn (ShippingCharge $charge) => (int) $charge->id === $shippingId
            );
            if (! $shipping) {
                throw ValidationException::withMessages(['shipping_area_id' => 'Select a currently available delivery area.']);
            }
            $shippingMinor = $freeDelivery ? 0 : ExactBdt::fromDecimal((string) $shipping->delivery_charge);
            $total = ExactBdt::add($subTotal, $shippingMinor);
            $customer = $this->identities->resolveOrCreate($name, $phone);
            $order = Order::create([
                'invoice_id' => $this->invoiceId(),
                'success_token' => Str::random(40),
                'campaign_id' => null, 'customer_id' => $customer->id,
                'storefront_account_id' => \Illuminate\Support\Facades\Auth::guard('storefront')->id(),
                'created_via' => Order::CREATED_VIA_FRONTEND,
                'customer_name' => $customer->name, 'phone' => $customer->phone,
                'address' => $address, 'delivery_area' => $shipping->area_name,
                'sub_total' => ExactBdt::toDecimal($subTotal),
                'shipping_charge' => ExactBdt::toDecimal($shippingMinor),
                'is_free_delivery' => $freeDelivery, 'cod_charge' => '0.00',
                'total_amount' => ExactBdt::toDecimal($total),
                'payment_method' => Order::PAYMENT_COD,
                'payment_status' => Order::PAYMENT_STATUS_COD_PENDING,
                'order_status' => Order::STATUS_PROCESSING,
                'is_fake' => false, 'customer_note' => $note ?: null,
                'source_ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'source_url' => route('storefront.checkout'),
            ]);
            $mapped = [];
            foreach ($checked as [$product, $variant, $qty, $unit, $lineTotal]) {
                $item = OrderItem::create([
                    'order_id' => $order->id, 'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => ExactBdt::toDecimal($unit),
                    'quantity' => $qty, 'discount_amount' => '0.00',
                    'total_price' => ExactBdt::toDecimal($lineTotal),
                ]);
                DB::table('order_items')->where('id', $item->id)->update([
                    'product_variant_id' => $variant->id,
                ]);
                $mapped[(int) $item->id] = (int) $variant->id;
            }
            $this->inventory->allocateStorefrontOrder((int) $order->id, $mapped);
            $this->idempotency->complete((string) $claim['checkout_request_id'], (int) $order->id);
            $this->assignment->assign($order);
            DB::table('cart_items')->where('cart_id', $cart->id)->delete();
            DB::table('carts')->where('id', $cart->id)->increment('lock_version', 1, [
                'updated_at' => now(), 'expires_at' => now()->addDays(30),
            ]);
            return (string) $order->success_token;
        }, 3);
    }

    private function invoiceId(): string
    {
        do {
            $invoice = 'INV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
        } while (DB::table('orders')->where('invoice_id', $invoice)->exists());
        return $invoice;
    }
}
