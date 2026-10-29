<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\PublicCatalog;
use App\Support\ExactBdt;
use App\Support\P02MigrationSafety;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Persistent cookie-owned cart; never reserves, consumes or releases inventory. */
final class GuestCartService
{
    public const MAX_LINES = 30;
    public const MAX_QUANTITY = 1000;

    public function __construct(private readonly PublicCatalog $catalog) {}

    public function assertEnabled(): void
    {
        $this->catalog->assertAvailable();
        abort_unless(config('storefront.p08_cart_test_enabled', false) === true, 404);
        if (request()->attributes->get('p58_production_commerce') === true) {
            app(\App\Support\P58ProductionStorefrontAccess::class)->assertApprovedDatabase();
        } elseif (request()->attributes->get('p32_local_commerce', false) === true) {
            app(\App\Support\P32LocalStorefrontAccess::class)->assertActualMainDatabase();
        } else {
            P02MigrationSafety::assertIsolatedTestDatabase();
        }
    }

    public function show(string $ownerHash): array
    {
        $this->assertEnabled();
        $this->validateOwner($ownerHash);
        $cart = DB::table('carts')->where('owner_hash', $ownerHash)
            ->where('expires_at', '>', now())->first();
        if ($cart === null) {
            return ['cart_id' => null, 'version' => 0, 'lines' => [], 'subtotal' => '0.00', 'can_checkout' => false];
        }
        $items = DB::table('cart_items')->where('cart_id', $cart->id)->orderBy('id')->get();
        $lines = [];
        $subtotal = 0;
        $checkoutReady = $items->isNotEmpty();
        foreach ($items as $item) {
            $variant = ProductVariant::with(['product.category', 'optionValues.option'])->find($item->product_variant_id);
            $product = $variant?->product;
            $valid = $variant !== null && $product !== null && $this->isPurchasable($product, $variant);
            $stock = $valid ? max(0, (int) $variant->stock) : 0;
            $current = $valid ? $this->price($product, $variant) : null;
            $quoted = ExactBdt::fromDecimal((string) $item->quoted_unit_price);
            $priceChanged = $current !== null && $current !== $quoted;
            $enough = $valid && (int) $item->quantity <= $stock;
            $checkoutReady = $checkoutReady && $enough && ! $priceChanged && $current > 0;
            $lineTotal = ExactBdt::multiply($quoted, (int) $item->quantity);
            $subtotal = ExactBdt::add($subtotal, $lineTotal);
            $labels = $variant?->optionValues?->map(
                static fn ($value) => ($value->option?->name ?? 'Option') . ': ' . $value->label
            )->implode(', ') ?? '';
            $lines[] = [
                'id' => (int) $item->id, 'product_id' => (int) $item->product_id,
                'variant_id' => (int) $item->product_variant_id,
                'name' => $product?->name ?? 'Unavailable product',
                'slug' => $product?->slug, 'option_label' => $labels,
                'sku' => $variant?->sku, 'quantity' => (int) $item->quantity,
                'stock' => $stock, 'available' => $valid, 'sufficient' => $enough,
                'unit_price' => ExactBdt::toDecimal($quoted),
                'current_price' => $current === null ? null : ExactBdt::toDecimal($current),
                'price_changed' => $priceChanged,
                'line_total' => ExactBdt::toDecimal($lineTotal),
                'is_free_delivery' => $valid && (bool) $product->is_free_delivery,
            ];
        }
        return [
            'cart_id' => (string) $cart->id, 'version' => (int) $cart->lock_version,
            'lines' => $lines, 'subtotal' => ExactBdt::toDecimal($subtotal),
            'can_checkout' => $checkoutReady,
        ];
    }

    /** A fresh cart accepts version 0; existing carts require an exact optimistic version. */
    public function add(string $ownerHash, int $variantId, int $quantity, int $expectedVersion): void
    {
        $this->assertEnabled();
        $this->validateOwner($ownerHash);
        $this->validateQuantity($quantity);
        if ($variantId < 1 || $expectedVersion < 0) {
            throw ValidationException::withMessages(['cart' => 'Invalid cart selection or version.']);
        }
        DB::transaction(function () use ($ownerHash, $variantId, $quantity, $expectedVersion): void {
            $cart = $this->lockedCart($ownerHash, $expectedVersion, true);
            $variant = ProductVariant::with(['product.category', 'optionValues.option'])->find($variantId);
            $product = $variant?->product;
            if (! $product || ! $this->isPurchasable($product, $variant)) {
                throw ValidationException::withMessages(['product_variant_id' => 'This selection is not available.']);
            }
            $price = $this->price($product, $variant);
            if ($price < 1) {
                throw ValidationException::withMessages(['product_variant_id' => 'This selection has no valid price.']);
            }
            $existing = DB::table('cart_items')->where('cart_id', $cart->id)
                ->where('product_variant_id', $variantId)->lockForUpdate()->first();
            $newQuantity = $quantity + (int) ($existing->quantity ?? 0);
            $this->validateQuantity($newQuantity);
            if ($newQuantity > (int) $variant->stock) {
                throw ValidationException::withMessages(['quantity' => 'Not enough stock is available.']);
            }
            if ($existing) {
                DB::table('cart_items')->where('id', $existing->id)->update([
                    'quantity' => $newQuantity, 'quoted_unit_price' => ExactBdt::toDecimal($price), 'updated_at' => now(),
                ]);
            } else {
                if (DB::table('cart_items')->where('cart_id', $cart->id)->count() >= self::MAX_LINES) {
                    throw ValidationException::withMessages(['cart' => 'Cart line limit reached.']);
                }
                DB::table('cart_items')->insert([
                    'cart_id' => $cart->id, 'product_id' => $product->id,
                    'product_variant_id' => $variantId, 'quantity' => $newQuantity,
                    'quoted_unit_price' => ExactBdt::toDecimal($price),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->bump($cart->id);
        }, 3);
    }

    /** Quantity set to zero removes an owned line. Repricing is explicit acknowledgement. */
    public function change(string $ownerHash, int $itemId, int $quantity, int $expectedVersion): void
    {
        $this->assertEnabled();
        $this->validateOwner($ownerHash);
        if ($itemId < 1 || $expectedVersion < 1 || $quantity < 0 || $quantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages(['quantity' => 'Invalid cart update.']);
        }
        DB::transaction(function () use ($ownerHash, $itemId, $quantity, $expectedVersion): void {
            $cart = $this->lockedCart($ownerHash, $expectedVersion, false);
            $item = DB::table('cart_items')->where('cart_id', $cart->id)
                ->where('id', $itemId)->lockForUpdate()->first();
            if (! $item) {
                throw ValidationException::withMessages(['cart' => 'Cart line not found in your cart.']);
            }
            if ($quantity === 0) {
                DB::table('cart_items')->where('id', $item->id)->where('cart_id', $cart->id)->delete();
            } else {
                $variant = ProductVariant::with(['product.category', 'optionValues.option'])->find($item->product_variant_id);
                $product = $variant?->product;
                if (! $product || ! $this->isPurchasable($product, $variant) || (int) $variant->stock < $quantity) {
                    throw ValidationException::withMessages(['quantity' => 'Product is unavailable or has insufficient stock.']);
                }
                DB::table('cart_items')->where('id', $itemId)->where('cart_id', $cart->id)->update([
                    'quantity' => $quantity, 'quoted_unit_price' => ExactBdt::toDecimal($this->price($product, $variant)),
                    'updated_at' => now(),
                ]);
            }
            $this->bump($cart->id);
        }, 3);
    }

    /** Internal caller already holds its own cart lock. */
    public function assertOwnedCart(string $ownerHash, int $expectedVersion): object
    {
        $this->assertEnabled();
        $this->validateOwner($ownerHash);
        return $this->lockedCart($ownerHash, $expectedVersion, false);
    }

    public function price(Product $product, ProductVariant $variant): int
    {
        return ExactBdt::fromDecimal((string) ($variant->price_override ?? $product->new_price));
    }

    public function isPurchasable(Product $product, ProductVariant $variant): bool
    {
        if (! $product->status || $product->trashed() || $product->inventory_version !== 1
            || ! $variant->is_active || $variant->trashed() || (int) $variant->product_id !== (int) $product->id) {
            return false;
        }
        if (! $this->catalog->eligibleProducts()->where('products.id', $product->id)->exists()) {
            return false;
        }
        if ($product->has_variants) {
            return $this->catalog->eligibleVariants($product)->contains('id', $variant->id);
        }
        return $variant->is_default && $variant->option_signature === 'default';
    }

    private function lockedCart(string $ownerHash, int $expectedVersion, bool $create): object
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Cart mutation must be in a transaction.');
        }
        $cart = DB::table('carts')->where('owner_hash', $ownerHash)->lockForUpdate()->first();
        if ($cart && Carbon::parse((string) $cart->expires_at)->isPast()) {
            DB::table('cart_items')->where('cart_id', $cart->id)->delete();
            DB::table('carts')->where('id', $cart->id)->delete();
            $cart = null;
        }
        if (! $cart && $create && $expectedVersion === 0) {
            $id = (string) Str::uuid();
            $created = DB::table('carts')->insertOrIgnore([
                'id' => $id, 'owner_hash' => $ownerHash, 'currency' => 'BDT',
                'lock_version' => 1, 'expires_at' => now()->addDays(30),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($created !== 1) {
                throw ValidationException::withMessages(['cart' => 'Another cart tab started first. Refresh to continue.']);
            }
            $cart = DB::table('carts')->where('id', $id)->lockForUpdate()->first();
            return $cart;
        }
        if (! $cart || (int) $cart->lock_version !== $expectedVersion) {
            throw ValidationException::withMessages(['cart' => 'Cart changed in another tab. Refresh before continuing.']);
        }
        return $cart;
    }

    private function bump(string $cartId): void
    {
        DB::table('carts')->where('id', $cartId)->increment('lock_version', 1, [
            'expires_at' => now()->addDays(30), 'updated_at' => now(),
        ]);
    }

    private function validateOwner(string $ownerHash): void
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/D', $ownerHash)) {
            throw new LogicException('Invalid guest owner identity.');
        }
    }

    private function validateQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be between 1 and 1000.']);
        }
    }
}
