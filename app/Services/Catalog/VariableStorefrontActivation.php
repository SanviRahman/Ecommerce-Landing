<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\PublicCatalog;
use App\Support\P32LocalStorefrontAccess;
use App\Support\P33CatalogAdminAccess;
use App\Support\P58ProductionStorefrontAccess;
use App\Support\VariantSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Explicit, admin-approved activation of an audited variable SKU catalog.
 * Requires the existing P33 admin role and verified test/local or P58
 * production database identity. It never reconciles legacy stock automatically.
 */
final class VariableStorefrontActivation
{
    public function activate(Product $product): void
    {
        P33CatalogAdminAccess::assertAllowed();

        DB::transaction(function () use ($product): void {
            // All variant writers lock the product first, then SKU rows in ID order.
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $this->assertReady($locked);
            $locked->update(['status' => true]);

            // The P32 request middleware grants checkout only when EVERY global
            // readiness condition is true. Validate that same gate *after* this
            // product becomes active, but before the transaction commits.
            $this->assertStorefrontCheckoutReady();
        }, 3);
    }

    /** Revalidate a product that was already live, e.g. after Product Edit. */
    public function verifyActive(Product $product): void
    {
        P33CatalogAdminAccess::assertAllowed();
        if (! $product->status) {
            $this->reject('The product is not active. Use the explicit activation action.');
        }
        $this->assertReady($product, requireStock: false);
        $this->assertStorefrontCheckoutReady();
    }

    public function pause(Product $product): void
    {
        P33CatalogAdminAccess::assertAllowed();
        DB::transaction(static function () use ($product): void {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! $locked->has_variants || (int) $locked->inventory_version !== 1) {
                throw ValidationException::withMessages([
                    'product' => 'This action only applies to managed variable products.',
                ]);
            }
            $locked->update(['status' => false]);
        }, 3);
    }

    private function assertStorefrontCheckoutReady(): void
    {
        if (config('app.env') === 'production') {
            $production = app(P58ProductionStorefrontAccess::class);
            $production->assertApprovedDatabase(request(), false);
            if (! $production->checkoutReady()) {
                $this->reject('Production checkout readiness failed. Check shipping, published homepage and managed SKU inventory before activation. No changes saved.');
            }
            return;
        }
        if (! P32LocalStorefrontAccess::matchesContext(
            (string) config('app.env'), config('app.debug') === true,
            (string) request()->ip(), (string) request()->getHost(),
            (int) request()->getPort()
        )) {
            return; // The isolated test-DB gate remains independently enforced.
        }
        $access = app(P32LocalStorefrontAccess::class);
        $access->assertActualMainDatabase();
        if (! $access->checkoutReady()) {
            $this->reject('Checkout readiness failed. Check global shipping charges, published homepage, managed stock totals and active option combinations before retrying. No product change was saved.');
        }
    }

    private function assertReady(Product $product, bool $requireStock = true): void
    {
        if ($product->trashed() || ! $product->has_variants || (int) $product->inventory_version !== 1) {
            $this->reject('Only a managed variable product can be activated. Prepare and convert a new zero-stock product first.');
        }
        if (! app(PublicCatalog::class)->eligibleCategories()->whereKey($product->category_id)->exists()) {
            $this->reject('The product category and its parent (if any) must be active.');
        }
        if (DB::table('campaign_product')->where('product_id', $product->id)->exists()) {
            $this->reject('Campaign-linked products cannot be activated by the storefront variant release workflow.');
        }

        $assigned = DB::table('product_option_assignments')
            ->where('product_id', $product->id)
            ->pluck('product_option_id')->map(static fn ($id) => (int) $id)->all();
        if (count($assigned) < 1 || count($assigned) > 4 || count($assigned) !== count(array_unique($assigned))) {
            $this->reject('Assign 1 to 4 distinct options and create complete combinations before activating.');
        }

        $variants = ProductVariant::query()->where('product_id', $product->id)
            ->orderBy('id')->lockForUpdate()->with('optionValues.option')->get();
        if ($variants->isEmpty() || $variants->count() > 200) {
            $this->reject('A managed product needs valid variant inventory.');
        }
        $total = 0;
        $activeCount = 0;
        $sellableCount = 0;
        foreach ($variants as $variant) {
            $stock = (int) $variant->stock;
            if ($stock < 0) {
                $this->reject('Negative variant inventory is not allowed.');
            }
            $total += $stock;
            if ($variant->option_signature === 'default') {
                if ($variant->is_active || $stock !== 0) {
                    $this->reject('The retired default SKU must be inactive with zero stock.');
                }
                continue;
            }
            if (! $variant->is_active) {
                continue;
            }
            $values = $variant->optionValues;
            if ($values->count() !== count($assigned)) {
                $this->reject('Active variant ' . $variant->sku . ' is missing an assigned option.');
            }
            $chosen = [];
            foreach ($values as $value) {
                $optionId = (int) $value->product_option_id;
                if (! $value->is_active || ! $value->option?->is_active
                    || ! in_array($optionId, $assigned, true)
                    || (int) $value->option->id !== $optionId
                    || isset($chosen[$optionId])) {
                    $this->reject('Active variant ' . $variant->sku . ' has an invalid or inactive option value.');
                }
                $chosen[$optionId] = (int) $value->id;
            }
            if (VariantSignature::fromOptionValues($chosen) !== $variant->option_signature) {
                $this->reject('Active variant ' . $variant->sku . ' has an incorrect option signature.');
            }
            $price = $variant->price_override ?? $product->new_price;
            if ((int) $price < 1) {
                $this->reject('Active variant ' . $variant->sku . ' must have a positive price.');
            }
            $activeCount++;
            if ($stock > 0) {
                $last = DB::table('inventory_movements')
                    ->where('product_variant_id', $variant->id)->orderByDesc('id')->first();
                if ($last === null || (int) $last->stock_after !== $stock) {
                    $this->reject('SKU ' . $variant->sku . ' stock does not match the audited inventory ledger.');
                }
                $sellableCount++;
            }
        }
        if ($total !== (int) $product->stock) {
            $this->reject('Product stock does not match its sum of managed variants.');
        }
        if ($activeCount === 0 || ($requireStock && $sellableCount === 0)) {
            $this->reject('At least one active, fully configured SKU is needed; initial activation also requires audited stock above zero.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['product' => $message]);
    }
}
