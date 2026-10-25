<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Support\P02MigrationSafety;
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
        P02MigrationSafety::assertIsolatedTestDatabase();
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
     * Prepare an unreconciled legacy/simple product without touching historic orders.
     * A positive opening balance is recorded as unverified until physically checked.
     */
    public function prepareSimple(Product $product): void
    {
        $this->assertTestSchema();
        DB::transaction(function () use ($product): void {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ((int) $locked->inventory_version !== 0 || (bool) $locked->has_variants
                || ProductVariant::withTrashed()->where('product_id', $locked->id)->exists()) {
                $this->reject('Product is already managed or contains variants.');
            }
            $stock = (int) $locked->stock;
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
                'Legacy opening balance, not independently reconciled');
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
                    ->where('product_option_id', $option->id)->where('is_active', true)->first();
                if ($value === null) {
                    $this->reject('Option value is inactive or belongs to another option.', 'options');
                }
                $byOption[(int) $option->id] = $valueId;
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
