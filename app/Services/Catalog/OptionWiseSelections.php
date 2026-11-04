<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Services\Inventory\StorefrontDeferredStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Independent product option choices with one Piece Count price and ONE shared physical balance. */
final class OptionWiseSelections
{
    public const CODES = ['color', 'size', 'weight', 'piece_count'];

    public function configured(Product $product): bool
    {
        return DB::table('product_piece_prices')->where('product_id', $product->id)->exists();
    }

    public function defaultInventory(Product $product): ?ProductVariant
    {
        return $product->variants()->where('is_default', true)->where('option_signature', 'default')
            ->where('is_active', true)->first();
    }

    /** Public matrix: no SKU, no cross-product combinations, and no price supplied by customer. */
    public function matrix(Product $product): array
    {
        $prices = DB::table('product_piece_prices')->where('product_id', $product->id)
            ->pluck('price', 'product_option_value_id')->mapWithKeys(
                static fn ($p, $id) => [(int) $id => (int) $p]
            )->all();
        if (!$prices) return ['options' => [], 'variants' => []];
        $assignedIds = DB::table('product_option_choices')->where('product_id', $product->id)
            ->pluck('product_option_value_id')->all();
        $options = ProductOption::query()->whereIn('code', self::CODES)->where('is_active', true)
            ->with(['values' => static fn ($q) => $q->whereIn('id', $assignedIds)->where('is_active', true)])
            ->orderBy('sort_order')->orderBy('id')->get();
        $groups = [];
        $first = [];
        $pieceOptionId = null;
        foreach ($options as $option) {
            if ($option->values->isEmpty()) continue;
            $values = [];
            foreach ($option->values as $value) {
                if ($option->code === 'piece_count' && !isset($prices[(int)$value->id])) continue;
                $values[] = ['id' => (int) $value->id, 'label' => $value->label,
                    'swatch' => $value->swatch_hex, 'configured' => true];
            }
            if (!$values) continue;
            $groups[] = ['id' => (int)$option->id, 'code' => $option->code,
                'name' => $option->name, 'values' => $values];
            $first[$option->code] = (int) $values[0]['id'];
            if ($option->code === 'piece_count') $pieceOptionId = (int) $option->id;
        }
        $default = $this->defaultInventory($product);
        $physical = $default ? (int) $default->stock : 0;
        return ['mode' => 'option_wise', 'options' => $groups, 'variants' => [],
            'prices' => $prices, 'selected' => $first, 'piece_option_id' => $pieceOptionId,
            'inventory_variant_id' => $default?->id,
            'stock' => $default ? StorefrontDeferredStock::availableForVariant((int)$default->id, $physical) : 0,
            'physical_stock' => $physical];
    }

    /** Validate a complete independent selection, and price ONLY the selected Piece Count. */
    public function quote(Product $product, array $selected): array
    {
        $matrix = $this->matrix($product);
        if (($matrix['mode'] ?? null) !== 'option_wise' || !$matrix['options']) {
            throw ValidationException::withMessages(['options' => 'Option-wise pricing is not configured for this product.']);
        }
        $allowedCodes = array_column($matrix['options'], 'code');
        if (array_diff(array_keys($selected), $allowedCodes) || count($selected) !== count($allowedCodes)) {
            throw ValidationException::withMessages(['options' => 'Select every offered Color, Size, Weight and Piece Count.']);
        }
        $snapshot = [];
        $labels = [];
        foreach ($matrix['options'] as $option) {
            $valueId = filter_var($selected[$option['code']] ?? null, FILTER_VALIDATE_INT);
            $value = collect($option['values'])->firstWhere('id', (int) $valueId);
            if (!$valueId || !$value) {
                throw ValidationException::withMessages(['options' => 'A selected product choice is no longer available.']);
            }
            $snapshot[(int) $option['id']] = (int) $valueId;
            $labels[] = $option['name'] . ': ' . $value['label'];
        }
        $pieceId = (int) ($selected['piece_count'] ?? 0);
        $price = (int) ($matrix['prices'][$pieceId] ?? 0);
        if ($price < 1) {
            throw ValidationException::withMessages(['options' => 'Select a valid Piece Count price.']);
        }
        ksort($snapshot, SORT_NUMERIC);
        return ['price' => $price, 'snapshot' => $snapshot,
            'signature' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'labels' => implode(' / ', $labels)];
    }

    /** Decode an item snapshot for checking current product pricing during initial allocation. */
    public function quoteSnapshot(Product $product, array $snapshot): array
    {
        $matrix = $this->matrix($product);
        $selected = [];
        foreach ($matrix['options'] ?? [] as $option) {
            if (!isset($snapshot[$option['id']])) {
                throw ValidationException::withMessages(['options' => 'An order option snapshot is incomplete.']);
            }
            $selected[$option['code']] = (int)$snapshot[$option['id']];
        }
        return $this->quote($product, $selected);
    }
}
