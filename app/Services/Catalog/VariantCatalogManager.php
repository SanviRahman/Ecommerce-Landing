<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\Order;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Support\P33CatalogAdminAccess;
use App\Support\VariantSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * P04 admin catalog.  TEST-ONLY until variable-product checkout is released.
 * Never edits historic order snapshots or invents pack-to-unit conversion.
 */
final class VariantCatalogManager
{
    public const OPTIONS = [
        'color' => 'Color',
        'size' => 'Size',
        'weight' => 'Weight',
        'piece_count' => 'Piece Count',
    ];

    private function reject(string $message, string $field = 'variant'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function assertTestSchema(): void
    {
        P33CatalogAdminAccess::assertAllowed();
        foreach (['product_options', 'product_option_values', 'product_variants',
            'product_variant_values', 'product_option_assignments', 'inventory_movements'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('P02 catalog schema is incomplete: ' . $table);
            }
        }
    }

    /** Creates ONLY the four approved dimension definitions; does not populate values. */
    public function bootstrapOptions(): void
    {
        $this->assertTestSchema();
        DB::transaction(function (): void {
            foreach (self::OPTIONS as $code => $name) {
                ProductOption::query()->firstOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'input_type' => 'select', 'is_active' => true]
                );
            }
        }, 3);
    }

    public function addOptionValue(ProductOption $option, array $data): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($option, $data): void {
            $option = ProductOption::whereKey($option->id)->lockForUpdate()->firstOrFail();
            if (! isset(self::OPTIONS[$option->code]) || ! $option->is_active) {
                $this->reject('Only active supported options can receive values.', 'option_id');
            }
            if ($option->code !== 'color' && ! empty($data['swatch_hex'])) {
                $this->reject('Color swatches are only allowed for Color.', 'swatch_hex');
            }
            if (ProductOptionValue::where('product_option_id', $option->id)
                ->where('code', $data['code'])->exists()) {
                $this->reject('This value code already exists.', 'code');
            }
            $option->values()->create([
                'code' => $data['code'], 'label' => $data['label'],
                'swatch_hex' => $option->code === 'color' ? ($data['swatch_hex'] ?? null) : null,
                'is_active' => true, 'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
        }, 3);
    }

    /**
     * Option values are shared reference data. Never change a referenced code or
     * deactivate an actively used choice without first retiring those variants.
     */
    public function updateOptionValue(ProductOption $option, ProductOptionValue $value, array $data): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($option, $value, $data): void {
            $option = ProductOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();
            $row = ProductOptionValue::query()->whereKey($value->id)
                ->where('product_option_id', $option->id)->lockForUpdate()->firstOrFail();
            if (! isset(self::OPTIONS[$option->code])) {
                $this->reject('Unsupported product option.', 'option_id');
            }
            if ($option->code !== 'color' && ! empty($data['swatch_hex'])) {
                $this->reject('Only Color can have a swatch.', 'swatch_hex');
            }
            $referenced = DB::table('product_variant_values')
                ->where('product_option_value_id', $row->id)->exists()
                || DB::table('product_option_choices')->where('product_option_value_id', $row->id)->exists()
                || DB::table('product_piece_prices')->where('product_option_value_id', $row->id)->exists();
            if ($referenced && $row->code !== $data['code']) {
                $this->reject('The code is locked because existing variants use this value.', 'code');
            }
            if (ProductOptionValue::query()->where('product_option_id', $option->id)
                ->where('code', $data['code'])->where('id', '!=', $row->id)->exists()) {
                $this->reject('This code already exists within the option.', 'code');
            }
            if ($referenced && ! $data['is_active'] && $row->is_active
                && (DB::table('product_option_choices')->where('product_option_value_id', $row->id)->exists()
                || DB::table('product_piece_prices')->where('product_option_value_id', $row->id)->exists()
                || DB::table('product_variant_values as pvv')
                    ->join('product_variants as pv', 'pv.id', '=', 'pvv.product_variant_id')
                    ->where('pvv.product_option_value_id', $row->id)
                    ->whereNull('pv.deleted_at')->where('pv.is_active', true)->exists())) {
                $this->reject('Active variants use this value. Deactivate those variants first.', 'is_active');
            }
            $row->update([
                'code' => $data['code'],
                'label' => trim($data['label']),
                'swatch_hex' => $option->code === 'color' ? ($data['swatch_hex'] ?: null) : null,
                'sort_order' => (int) $data['sort_order'],
                'is_active' => (bool) $data['is_active'],
            ]);
        }, 3);
    }

    public function deleteOptionValue(ProductOption $option, ProductOptionValue $value): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($option, $value): void {
            $row = ProductOptionValue::query()->whereKey($value->id)
                ->where('product_option_id', $option->id)->lockForUpdate()->firstOrFail();
            if (DB::table('product_variant_values')
                ->where('product_option_value_id', $row->id)->exists()
                || DB::table('product_option_choices')->where('product_option_value_id', $row->id)->exists()
                || DB::table('product_piece_prices')->where('product_option_value_id', $row->id)->exists()) {
                $this->reject('Value is used by a variant. Keep it for history; archive the variant before disabling this choice.', 'value');
            }
            $row->delete();
        }, 3);
    }

    /**
     * Prepare an unreconciled legacy/simple product without touching historic orders.
     * A positive opening balance is recorded as unverified until physically checked.
     * The explicit option-wise flag is ONLY used by the validated, transactional
     * Option-wise Pricing save action. All existing callers keep the old safety gate.
     */
    public function prepareSimple(Product $product, bool $approvedOptionWisePromotion = false): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($product, $approvedOptionWisePromotion): void {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->inventory_version !== 0 || (bool) $locked->has_variants
                || ProductVariant::withTrashed()->where('product_id', $locked->id)->exists()) {
                $this->reject('Product is already managed or contains variants.');
            }
            $stock = (int) $locked->stock;
            // Main live inventory is never silently reconciled from legacy stock
            // or order history. Restrict preparation to new zero-stock products;
            // the P33 gate independently verifies the production DB identity.
            $mainInventoryDatabase = config('app.env') === 'production'
                || \App\Support\P32LocalStorefrontAccess::matchesContext(
                    (string) config('app.env'), config('app.debug') === true,
                    (string) request()->ip(), (string) request()->getHost(), (int) request()->getPort()
                );
            if ($approvedOptionWisePromotion) {
                // Legacy orders do not carry a managed SKU reservation. An open
                // order could later consume physical stock without using this
                // shared ledger. Block conversion until it is closed/reconciled.
                $unfinishedOrders = DB::table('order_items as i')
                    ->join('orders as o', 'o.id', '=', 'i.order_id')
                    ->where('i.product_id', $locked->id)
                    ->where(static function ($query): void {
                        $query->whereNull('o.order_status')->orWhereNotIn('o.order_status', [
                            Order::STATUS_DELIVERED, Order::STATUS_CANCELLED,
                            Order::STATUS_CANCELED, Order::STATUS_FAKE,
                            Order::STATUS_COURIER_CANCELLED,
                        ]);
                    })->exists();
                if ($unfinishedOrders) {
                    $this->reject('Finish or reconcile pending orders for this product before adding Option-wise Pricing. Existing physical stock cannot be safely reserved twice.', 'options');
                }
                // Existing stock is retained AS IS and becomes the opening
                // shared balance; past completed order items are not touched.
            } elseif ($mainInventoryDatabase && ($stock !== 0
                || DB::table('order_items')->where('product_id', $locked->id)->exists()
                || DB::table('campaign_product')->where('product_id', $locked->id)->exists())) {
                $this->reject('On the main inventory database, only new zero-stock products with no campaign/order history can be prepared. Reconcile legacy inventory separately.');
            }
            if ($stock < 0 || $stock > 2_147_483_647) {
                $this->reject('Legacy stock is outside supported limits.');
            }
            $businessCode = trim((string) ($locked->product_code ?? ''));
            $sku = preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,99}\z/D', $businessCode) === 1
                && ! ProductVariant::withTrashed()->where('sku', $businessCode)->exists()
                ? $businessCode : 'LEGACY-' . $locked->id;
            if (ProductVariant::withTrashed()->where('sku', $sku)->exists()
                || DB::table('inventory_movements')->where('operation_key', 'opening:v1:product:' . $locked->id)->exists()) {
                $this->reject('Default SKU/opening key collision; reconcile manually before retrying.');
            }
            $variant = ProductVariant::create([
                'product_id' => $locked->id,
                'sku' => $sku, 'option_signature' => 'default',
                'stock' => $stock, 'is_default' => true, 'is_active' => (bool) $locked->status,
            ]);
            $this->recordMovement($variant, 'opening', $stock, 0, $stock,
                'opening:v1:product:' . $locked->id,
                $approvedOptionWisePromotion
                    ? 'Simple Product converted to Option-wise shared stock (existing balance, unverified)'
                    : 'Legacy opening balance, not independently reconciled',
                $approvedOptionWisePromotion ? (int) auth()->id() : null);
            $locked->update(['inventory_version' => 1, 'has_variants' => false]);
        }, 3);
    }

    /**
     * Conversion does not silently distribute existing stock across combinations.
     * Caller must first reduce physical default stock to zero via approved movements.
     */
    public function convertToVariable(Product $product): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($product): void {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->inventory_version !== 1 || $locked->has_variants) {
                $this->reject('Prepare a managed simple product before conversion.');
            }
            $variants = ProductVariant::where('product_id', $locked->id)->orderBy('id')->lockForUpdate()->get();
            if ($variants->count() !== 1 || ! $variants->first()->is_default
                || $variants->first()->option_signature !== 'default'
                || (int) $variants->first()->stock !== 0 || (int) $locked->stock !== 0) {
                $this->reject('Conversion requires exactly one zero-stock default variant.');
            }
            $variant = $variants->first();
            if (DB::table('order_items')->where('product_variant_id', $variant->id)->exists()) {
                $this->reject('Cannot convert a SKU referenced by managed order history.');
            }
            $variant->update(['is_active' => false, 'is_default' => false]);
            // Legacy campaign checkout cannot safely order optioned products yet.
            $locked->update(['has_variants' => true, 'status' => false]);
        }, 3);
    }

    /**
     * @param array<string,int> $selectedByCode
     */
    public function createVariant(Product $product, string $sku, array $selectedByCode,
        ?int $price, ?int $oldPrice, bool $active): ProductVariant
    {
        $this->assertTestSchema();
        return DB::transaction(function () use ($product, $sku, $selectedByCode, $price, $oldPrice, $active): ProductVariant {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! $locked->has_variants || (int) $locked->inventory_version !== 1) {
                $this->reject('Product must be converted to Variable before adding combinations.');
            }
            if (ProductVariant::where('product_id', $locked->id)->withTrashed()->count() >= 200) {
                $this->reject('Maximum 200 variants allowed per product.');
            }
            if (ProductVariant::withTrashed()->where('sku', $sku)->exists()) {
                $this->reject('SKU already exists, including archived records.', 'sku');
            }
            if (count($selectedByCode) < 1 || count($selectedByCode) > 4) {
                $this->reject('Select at least one valid option value.', 'options');
            }
            $optionMap = ProductOption::query()
                ->whereIn('code', array_keys($selectedByCode))
                ->where('is_active', true)->get()->keyBy('code');
            if ($optionMap->count() !== count($selectedByCode)) {
                $this->reject('Unsupported or inactive option.', 'options');
            }
            $byOption = [];
            foreach ($selectedByCode as $code => $valueId) {
                if (! isset(self::OPTIONS[$code]) || ! is_int($valueId) || $valueId < 1) {
                    $this->reject('Invalid option value submitted.', 'options');
                }
                $option = $optionMap->get($code);
                $value = ProductOptionValue::whereKey($valueId)
                    ->where('product_option_id', $option->id)->where('is_active', true)->lockForUpdate()->first();
                if ($value === null) {
                    $this->reject('Option value is inactive or belongs to another option.', 'options');
                }
                $byOption[(int) $option->id] = $valueId;
            }
            // Adding a NEW dimension to a live product would instantly make
            // every existing combination incomplete and revoke checkout globally.
            // Require the admin to pause the product before changing its option schema.
            if ($locked->status) {
                $assigned = DB::table('product_option_assignments')
                    ->where('product_id', $locked->id)
                    ->pluck('product_option_id')->map(static fn ($id) => (int) $id)->all();
                $chosenOptionIds = array_map('intval', array_keys($byOption));
                sort($assigned, SORT_NUMERIC);
                sort($chosenOptionIds, SORT_NUMERIC);
                if ($assigned !== $chosenOptionIds) {
                    $this->reject('Pause this variable product before changing its assigned option dimensions.', 'options');
                }
                if ($active && (int) ($price ?? $locked->new_price) < 1) {
                    $this->reject('Active storefront combinations must have a positive price.', 'price_override');
                }
            }
            try {
                $signature = VariantSignature::fromOptionValues($byOption);
            } catch (InvalidArgumentException $exception) {
                $this->reject($exception->getMessage(), 'options');
            }
            if (ProductVariant::where('product_id', $locked->id)->withTrashed()
                ->where('option_signature', $signature)->exists()) {
                $this->reject('This exact combination already exists.', 'options');
            }
            $variant = ProductVariant::create([
                'product_id' => $locked->id, 'sku' => $sku,
                'option_signature' => $signature, 'price_override' => $price,
                'old_price_override' => $oldPrice, 'stock' => 0,
                'is_default' => false, 'is_active' => $active,
            ]);
            foreach ($byOption as $optionId => $valueId) {
                DB::table('product_option_assignments')->insertOrIgnore([
                    'product_id' => $locked->id, 'product_option_id' => $optionId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('product_variant_values')->insert([
                    'product_variant_id' => $variant->id, 'product_option_id' => $optionId,
                    'product_option_value_id' => $valueId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->recordMovement($variant, 'opening', 0, 0, 0,
                'opening:p04:variant:' . $variant->id, 'New combination starts with zero stock');
            return $variant;
        }, 3);
    }

    /**
     * Edit one existing combination from Product Edit (not a new SKU).
     * Preserve its SKU ID, exact stock, inventory ledger and historical orders.
     * A SKU already referenced by an order cannot have its option identity
     * rewritten; that would misrepresent a customer's original selection.
     *
     * @param array<string,int> $selectedByCode
     * @return bool Whether the selected combination actually changed.
     */
    public function updateVariantOptionValues(Product $product, ProductVariant $variant,
        array $selectedByCode, string $expectedSignature): bool
    {
        $this->assertTestSchema();

        return DB::transaction(function () use ($product, $variant, $selectedByCode, $expectedSignature): bool {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (! $locked->has_variants || (int) $locked->inventory_version !== 1) {
                $this->reject('Only an existing managed variable SKU can change its variation.', 'edit_variation');
            }
            $row = ProductVariant::query()->where('product_id', $locked->id)
                ->whereKey($variant->id)->lockForUpdate()->first();
            if (! $row || $row->is_default || $row->option_signature === 'default') {
                $this->reject('Choose a valid sellable SKU for this product.', 'edit_variation');
            }
            if ($row->option_signature !== $expectedSignature) {
                $this->reject('SKU options changed in another tab. Reload Product Edit.', 'edit_variation');
            }
            if (count($selectedByCode) < 1 || count($selectedByCode) > 4
                || count(array_diff(array_keys($selectedByCode), array_keys(self::OPTIONS))) > 0) {
                $this->reject('Choose at least one valid Color, Size, Weight or Piece Count.', 'edit_variation');
            }
            $options = ProductOption::query()->whereIn('code', array_keys($selectedByCode))
                ->get()->keyBy('code');
            if ($options->count() !== count($selectedByCode)) {
                $this->reject('An option is missing. Reload Product Edit.', 'edit_variation');
            }
            $byOption = [];
            foreach ($selectedByCode as $code => $id) {
                if (! is_int($id) || $id < 1) {
                    $this->reject('Invalid option value.', 'edit_variation');
                }
                $option = $options->get($code);
                $value = ProductOptionValue::query()->whereKey($id)
                    ->where('product_option_id', $option->id)->first();
                if (! $value) {
                    $this->reject('Selected value does not belong to ' . self::OPTIONS[$code] . '.', 'edit_variation');
                }
                $byOption[(int) $option->id] = (int) $value->id;
            }

            try {
                $signature = VariantSignature::fromOptionValues($byOption);
            } catch (InvalidArgumentException $error) {
                $this->reject($error->getMessage(), 'edit_variation');
            }
            if ($signature === $row->option_signature) {
                return false; // Allow unchanged old options without rewriting history.
            }
            foreach ($selectedByCode as $code => $id) {
                $option = $options->get($code);
                if (! $option->is_active || ! ProductOptionValue::query()->whereKey($id)
                    ->where('product_option_id', $option->id)->where('is_active', true)->exists()) {
                    $this->reject('Activate the selected ' . self::OPTIONS[$code]
                        . ' value in Variant Options before saving.', 'edit_variation');
                }
            }
            if (DB::table('order_items')->where('product_variant_id', $row->id)->exists()) {
                $this->reject('This SKU is used by an order. Its variation cannot be changed without altering order history.', 'edit_variation');
            }
            if (ProductVariant::query()->withTrashed()->where('product_id', $locked->id)
                ->where('id', '!=', $row->id)->where('option_signature', $signature)->exists()) {
                $this->reject('Another SKU already uses this option combination.', 'edit_variation');
            }

            $currentAssigned = DB::table('product_option_assignments')
                ->where('product_id', $locked->id)->pluck('product_option_id')
                ->map(static fn ($id) => (int) $id)->sort()->values()->all();
            $requestedAssigned = array_map('intval', array_keys($byOption));
            sort($requestedAssigned, SORT_NUMERIC);
            if ($currentAssigned !== $requestedAssigned) {
                // Re-assigning a dimension would invalidate every other SKU.
                // Limit it to a product that has never had another variable SKU
                // or an order, including archived/history records.
                $otherVariations = ProductVariant::query()->withTrashed()
                    ->where('product_id', $locked->id)
                    ->where('id', '!=', $row->id)
                    ->where('option_signature', '!=', 'default')->exists();
                if ($otherVariations || DB::table('order_items')
                    ->where('product_id', $locked->id)->exists()) {
                    $this->reject('This product has other/historical SKUs. Keep the same variation dimensions and edit their values only.', 'edit_variation');
                }
                DB::table('product_option_assignments')->where('product_id', $locked->id)->delete();
                foreach ($requestedAssigned as $optionId) {
                    DB::table('product_option_assignments')->insert([
                        'product_id' => $locked->id, 'product_option_id' => $optionId,
                        'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            // Existing product variant ID/SKU/price/stock never change here.
            // The transaction and the unique signature index protect integrity.
            DB::table('product_variant_values')->where('product_variant_id', $row->id)->delete();
            foreach ($byOption as $optionId => $valueId) {
                DB::table('product_variant_values')->insert([
                    'product_variant_id' => $row->id,
                    'product_option_id' => $optionId,
                    'product_option_value_id' => $valueId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $row->update(['option_signature' => $signature]);
            return true;
        }, 3);
    }

    /**
     * Common 1/2-piece labels for the Product Create form. These are separate
     * sellable pack SKUs, not an assumed conversion of individual-unit stock.
     * The existing Option Values manager remains available for other packs.
     *
     * @return array<int,int> Piece count => option value ID
     */
    public function standardPieceCountValues(): array
    {
        $this->assertTestSchema();

        return DB::transaction(function (): array {
            $option = ProductOption::query()->firstOrCreate(
                ['code' => 'piece_count'],
                ['name' => 'Piece Count', 'input_type' => 'select', 'is_active' => true]
            );
            $option = ProductOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();
            if (! $option->is_active) {
                $this->reject('Enable Piece Count in Variant Options before adding piece packs.', 'initial_piece_variations');
            }

            $ids = [];
            foreach ([1, 2] as $count) {
                $value = ProductOptionValue::query()->firstOrCreate(
                    ['product_option_id' => $option->id, 'code' => 'pack_' . $count],
                    ['label' => $count . ' Piece' . ($count === 1 ? '' : 's'),
                        'is_active' => true, 'sort_order' => $count]
                );
                if (! $value->is_active) {
                    $this->reject('Reactivate ' . $count . '-piece value in Variant Options first.', 'initial_piece_variations');
                }
                $ids[$count] = (int) $value->id;
            }
            return $ids;
        }, 3);
    }

    /**
     * Set the displayed stock to an exact quantity while reusing the existing
     * optimistic-lock and inventory-ledger adjustment path. No checkout or
     * order status transitions occur here.
     */
    public function setStockQuantity(Product $product, ProductVariant $variant,
        int $quantity, int $expectedStock, int $actorId, string $reason = 'Admin entered exact SKU stock count'): void
    {
        $this->assertTestSchema();
        if ($quantity < 0 || $quantity > 1_000_000 || $expectedStock < 0) {
            $this->reject('Stock must be a nonnegative number up to 1,000,000.', 'stock');
        }

        DB::transaction(function () use ($product, $variant, $quantity, $expectedStock, $actorId, $reason): void {
            // Other writers use product -> variant lock order too.
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $row = ProductVariant::query()->where('product_id', $locked->id)
                ->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            if ((int) $row->stock !== $expectedStock) {
                $this->reject('Stock changed in another order or browser tab. Reload and try again.', 'stock');
            }
            if ($locked->has_variants && $row->option_signature === 'default') {
                $this->reject('The retired default SKU cannot receive stock.', 'stock');
            }
            if ($quantity === $expectedStock) {
                return; // No fabricated audit delta for an unchanged number.
            }
            // Never reduce a SKU below quantities already reserved by unconfirmed
            // storefront orders. Such reductions would make confirmation fail.
            if (Schema::hasColumn('orders', 'storefront_stock_state')) {
                $reserved = (int) DB::table('order_items as i')
                    ->join('orders as o', 'o.id', '=', 'i.order_id')
                    ->where('i.product_variant_id', $row->id)
                    ->where('o.storefront_stock_state', \App\Services\Inventory\StorefrontDeferredStock::RESERVED)
                    ->whereNull('o.deleted_at')->whereNull('i.deleted_at')
                    ->sum('i.quantity');
                if ($quantity < $reserved) {
                    $this->reject('Stock cannot be lower than ' . $reserved . ' units reserved in pending orders.', 'stock');
                }
            }
            $this->adjustStock($locked, $row, $quantity - $expectedStock,
                $expectedStock, $reason, $actorId);
        }, 3);
    }

    public function updateVariant(Product $product, ProductVariant $variant, array $attributes): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($product, $variant, $attributes): void {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $row = ProductVariant::whereKey($variant->id)->where('product_id', $locked->id)
                ->lockForUpdate()->firstOrFail();
            if ((bool) $row->is_default || $row->option_signature === 'default' || ! $locked->has_variants) {
                $this->reject('A legacy default SKU cannot be edited as a variable combination.');
            }
            if ($attributes['sku'] !== $row->sku) {
                if (DB::table('order_items')->where('product_variant_id', $row->id)->exists()) {
                    $this->reject('SKU of a previously ordered variant is immutable.', 'sku');
                }
                if (ProductVariant::withTrashed()->where('sku', $attributes['sku'])
                    ->where('id', '!=', $row->id)->exists()) {
                    $this->reject('SKU already exists.', 'sku');
                }
            }
            $row->update($attributes);
        }, 3);
    }

    public function adjustStock(Product $product, ProductVariant $variant, int $delta,
        int $expectedStock, string $reason, int $actorId): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($product, $variant, $delta, $expectedStock, $reason, $actorId): void {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->inventory_version !== 1) {
                $this->reject('Prepare inventory before editing managed stock.');
            }
            $row = ProductVariant::whereKey($variant->id)->where('product_id', $locked->id)
                ->lockForUpdate()->firstOrFail();
            if ($locked->has_variants && $row->option_signature === 'default') {
                $this->reject('Retired default SKU cannot receive stock in variable-product mode.');
            }
            if (mb_strlen(trim($reason)) < 8 || mb_strlen($reason) > 255 || $actorId < 1) {
                $this->reject('A valid actor and meaningful stock adjustment reason are required.', 'reason');
            }
            $before = (int) $row->stock;
            if ($before !== $expectedStock) {
                $this->reject('Stock changed in another tab. Reload before retrying.', 'expected_stock');
            }
            if ($delta === 0 || $delta < -1_000_000 || $delta > 1_000_000) {
                $this->reject('Adjustment must be a nonzero delta within approved limits.', 'delta');
            }
            $after = $before + $delta;
            if ($after < 0 || $after > 4_294_967_295) {
                $this->reject('Stock cannot be negative or exceed database capacity.', 'delta');
            }
            // All writers (including the standalone Stock page) must respect
            // unconfirmed campaign/storefront holds before reducing inventory.
            if ($delta < 0 && Schema::hasColumn('orders', 'storefront_stock_state')) {
                $reserved = (int) DB::table('order_items as i')
                    ->join('orders as o', 'o.id', '=', 'i.order_id')
                    ->where('i.product_variant_id', $row->id)
                    ->where('o.storefront_stock_state', \App\Services\Inventory\StorefrontDeferredStock::RESERVED)
                    ->whereNull('i.deleted_at')->whereNull('o.deleted_at')
                    ->sum('i.quantity');
                if ($after < $reserved) {
                    $this->reject('Stock cannot be lowered below quantities held by unconfirmed orders.', 'stock');
                }
            }
            $last = DB::table('inventory_movements')->where('product_variant_id', $row->id)
                ->orderByDesc('id')->first();
            if ($last === null || (int) $last->stock_after !== $before) {
                $this->reject('Inventory ledger mismatch. Reconcile before changing stock.');
            }
            $row->update(['stock' => $after]);
            $this->recordMovement($row, 'adjustment', $delta, $before, $after,
                'admin-adjust:p04:' . Str::uuid(), trim($reason), $actorId);
            $total = (int) DB::table('product_variants')->where('product_id', $locked->id)
                ->whereNull('deleted_at')->sum('stock');
            if ($total > 4_294_967_295) {
                $this->reject('Product stock total exceeds database capacity.');
            }
            $locked->update(['stock' => $total]);
        }, 3);
    }

    private function recordMovement(ProductVariant $variant, string $kind, int $delta,
        int $before, int $after, string $key, string $reason, ?int $actorId = null): void
    {
        DB::table('inventory_movements')->insert([
            'product_variant_id' => $variant->id, 'order_item_id' => null,
            'event_uuid' => (string) Str::uuid(), 'operation_key' => $key,
            'kind' => $kind, 'quantity_delta' => $delta,
            'stock_before' => $before, 'stock_after' => $after,
            'actor_id' => $actorId, 'reason' => $reason,
            'metadata' => json_encode(['source' => 'p04_admin', 'reconciled' => false], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
