<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Services\Inventory\StorefrontDeferredStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Read-only P07 browse contract. Checkout and legacy campaigns remain independent. */
final class PublicCatalog
{
    private ?bool $manualOrderColumnAvailable = null;

    /** Public read-only pages, with no QA consent or admin session required.
     * No inventory, cart or checkout capability is granted by this check.
     */
    public function assertAvailable(): void
    {
        // Fail safely if this deployment has not installed the storefront schema.
        // Never expose a draft or try to migrate from a customer request.
        foreach ([
            'storefront_pages', 'storefront_revisions', 'homepage_sections',
            'homepage_section_items', 'homepage_section_products',
            'homepage_section_categories', 'categories', 'products',
            'product_variants', 'product_variant_values', 'product_option_assignments',
        ] as $table) {
            abort_unless(\Illuminate\Support\Facades\Schema::hasTable($table), 503,
                'The storefront database schema is not ready.');
        }
    }

    /** Only top-level and one-level child categories with an active, undeleted parent. */
    public function eligibleCategories(): Builder
    {
        return Category::query()->where('status', true)
            ->where(function (Builder $query): void {
                $query->whereNull('parent_id')
                    ->orWhereHas('parent', static function (Builder $parent): void {
                        $parent->where('status', true)->whereNull('parent_id');
                    });
            });
    }

    public function navigationCategories(): Collection
    {
        $categories = $this->eligibleCategories()
            ->with('media')->orderBy('sort_order')->orderBy('name')->limit(180)->get();
        // Bounded, explicit grouping; does not expose third-level or disabled nodes.
        $parents = $categories->whereNull('parent_id')->values();
        return $parents->map(function (Category $parent) use ($categories): Category {
            $parent->setRelation('children', $categories->where('parent_id', $parent->id)->values());
            return $parent;
        });
    }

    /** Products must be active and belong to an eligible active category. */
    public function eligibleProducts(): Builder
    {
        return Product::query()->where('products.status', true)
            ->whereHas('category', function (Builder $category): void {
                $category->where('status', true)
                    ->where(function (Builder $query): void {
                        $query->whereNull('parent_id')
                            ->orWhereHas('parent', static function (Builder $parent): void {
                                $parent->where('status', true)->whereNull('parent_id');
                            });
                    });
            })
            ->where(function (Builder $product): void {
                $product->where('has_variants', false)
                    ->orWhereHas('variants', static function (Builder $variant): void {
                        // Only genuine, complete optioned combinations can make a
                        // variable product publicly browseable; retired default SKU excluded.
                        $variant->where('is_active', true)->where('is_default', false)
                            ->whereHas('optionValues', static function (Builder $value): void {
                                $value->where('is_active', true)->whereHas('option',
                                    static fn (Builder $option) => $option->where('is_active', true));
                            })
                            ->whereRaw('(SELECT COUNT(*) FROM product_variant_values pv WHERE pv.product_variant_id = product_variants.id) = (SELECT COUNT(*) FROM product_option_assignments pa WHERE pa.product_id = product_variants.product_id)')
                            ->whereDoesntHave('optionValues', static function (Builder $value): void {
                                $value->where('is_active', false)
                                    ->orWhereHas('option', static fn (Builder $option) => $option->where('is_active', false));
                            });
                    });
            });
    }

    public function priceExpression(): string
    {
        return 'CASE WHEN products.has_variants = 1 THEN '
            . '(SELECT MIN(COALESCE(v.price_override, products.new_price)) FROM product_variants v '
            . 'WHERE v.product_id = products.id AND v.is_active = 1 AND v.deleted_at IS NULL) '
            . 'ELSE products.new_price END';
    }

    /**
     * Published homepage category selections are membership-only: display order
     * is owned by the same category sort_order used in public navigation.
     */
    public function orderedCategoryIds(array $ids, Collection $categories): array
    {
        return collect($ids)->filter(static fn ($id): bool => $categories->has($id))
            ->sort(static function ($leftId, $rightId) use ($categories): int {
                $left = $categories->get($leftId);
                $right = $categories->get($rightId);
                $position = static fn (Category $category): array => [
                    (int) ($category->parent?->sort_order ?? $category->sort_order),
                    $category->parent_id === null ? 0 : 1,
                    $category->parent_id === null ? 0 : (int) $category->sort_order,
                    (string) ($category->parent?->name ?? $category->name),
                    (string) $category->name,
                    (int) $category->id,
                ];
                return $position($left) <=> $position($right);
            })->values()->all();
    }

    /** Sorting inputs are allowlisted; no raw user SQL is interpolated. */
    public function sortedProducts(Builder $query, string $sort): Builder
    {
        // Existing public catalog keeps working if code is deployed before the
        // reviewed additive migration; manual ordering activates after migrate.
        if ($sort === 'manual' && ! ($this->manualOrderColumnAvailable ??= Schema::hasColumn('products', 'sort_order'))) {
            $sort = 'newest';
        }
        $query->select('products.*')->selectRaw('(' . $this->priceExpression() . ') AS catalog_price')
            ->with(['category', 'media', 'variants.optionValues.option']);
        return match ($sort) {
            'manual' => $query
                // Parent category -> direct products -> ordered subcategories -> ordered products.
                // All joins are one-to-one and do not widen public product eligibility.
                ->join('categories as merch_category', 'merch_category.id', '=', 'products.category_id')
                ->leftJoin('categories as merch_parent', 'merch_parent.id', '=', 'merch_category.parent_id')
                ->orderByRaw('COALESCE(merch_parent.sort_order, merch_category.sort_order) ASC')
                ->orderByRaw('CASE WHEN merch_category.parent_id IS NULL THEN 0 ELSE 1 END ASC')
                ->orderBy('merch_category.sort_order')->orderBy('merch_category.name')
                ->orderBy('products.sort_order')->orderByDesc('products.id'),
            'price_asc' => $query->orderBy('catalog_price')->orderBy('products.id'),
            'price_desc' => $query->orderByDesc('catalog_price')->orderBy('products.id'),
            'name' => $query->orderBy('products.name')->orderBy('products.id'),
            default => $query->orderByDesc('products.id'),
        };
    }

    public function product(string $slug): Product
    {
        $product = $this->eligibleProducts()->with(['category', 'media', 'variants.optionValues.option'])
            ->where('slug', $slug)->firstOrFail();
        if ($product->has_variants && $this->eligibleVariants($product)->isEmpty()) {
            abort(404);
        }
        return $product;
    }

    /** Reject incomplete, inactive or foreign-value configurations from public options. */
    public function eligibleVariants(Product $product): Collection
    {
        if (! $product->has_variants) {
            return collect();
        }
        $assignedOptionIds = \Illuminate\Support\Facades\DB::table('product_option_assignments')
            ->where('product_id', $product->id)->pluck('product_option_id')->map(static fn ($value) => (int) $value)->all();
        if ($assignedOptionIds === [] || count($assignedOptionIds) > 4) {
            return collect();
        }
        $product->loadMissing('variants.optionValues.option');
        return $product->variants->filter(static function (ProductVariant $variant) use ($assignedOptionIds): bool {
            if (! $variant->is_active || $variant->trashed() || $variant->is_default) {
                return false;
            }
            $values = $variant->optionValues;
            if ($values->count() !== count($assignedOptionIds)) {
                return false;
            }
            $selectedOptionIds = [];
            foreach ($values as $value) {
                if (! $value->is_active || ! $value->option || ! $value->option->is_active
                    || ! in_array((int) $value->product_option_id, $assignedOptionIds, true)) {
                    return false;
                }
                $selectedOptionIds[] = (int) $value->product_option_id;
            }
            return count(array_unique($selectedOptionIds)) === count($assignedOptionIds);
        })->values();
    }

    public function productImage(Product $product): string
    {
        return $product->getFirstMediaUrl('product_thumbnail') ?: asset('frontend/images/no-image.svg');
    }

    public function categoryImage(Category $category): string
    {
        return $category->getFirstMediaUrl('category_image') ?: asset('frontend/images/no-image.svg');
    }

    /** Currency values are existing integer BDT values; no guest price input is accepted. */
    public function displayPrice(Product $product): int
    {
        if (! $product->has_variants) {
            return (int) $product->new_price;
        }
        $variants = $this->eligibleVariants($product);
        return $variants->isEmpty() ? (int) $product->new_price
            : (int) $variants->min(static fn (ProductVariant $v) => (int) ($v->price_override ?? $product->new_price));
    }

    public function displayStock(Product $product): int
    {
        if ($product->has_variants) {
            return (int) $this->eligibleVariants($product)->sum(
                static fn (ProductVariant $variant) => StorefrontDeferredStock::availableForVariant(
                    (int) $variant->id, (int) $variant->stock)
            );
        }
        $default = $product->variants->first(
            static fn (ProductVariant $variant) => (bool) $variant->is_default && ! $variant->trashed()
        );
        return $default
            ? StorefrontDeferredStock::availableForVariant((int) $default->id, (int) $default->stock)
            : max(0, (int) $product->stock);
    }

    /**
     * Count PHYSICAL SKU units only. Processing storefront orders create a
     * reservation, not an inventory movement; the public product detail must
     * not mistake those temporary holds for a completed stock decrement.
     *
     * Purchase eligibility stays on displayStock()/availableForVariant().
     */
    public function physicalStock(Product $product): int
    {
        if ($product->has_variants) {
            return (int) $this->eligibleVariants($product)->sum(
                static fn (ProductVariant $variant): int => max(0, (int) $variant->stock)
            );
        }
        $default = $product->variants->first(
            static fn (ProductVariant $variant) => (bool) $variant->is_default && ! $variant->trashed()
        );
        return $default
            ? max(0, (int) $default->stock)
            : max(0, (int) $product->stock);
    }

    /** Trusted, code-owned option matrix for read-only product-page selection. */
    public function selectionData(Product $product): array
    {
        $variants = $this->eligibleVariants($product);
        $options = [];
        $rows = [];
        foreach ($variants as $variant) {
            $selected = [];
            foreach ($variant->optionValues as $value) {
                $option = $value->option;
                $optionId = (int) $option->id;
                $options[$optionId] ??= [
                    'id' => $optionId, 'name' => $option->name,
                    'sort_order' => (int) $option->sort_order, 'values' => [],
                ];
                $options[$optionId]['values'][(int) $value->id] = [
                    'id' => (int) $value->id, 'label' => $value->label,
                    'swatch' => $value->swatch_hex,
                    'sort_order' => (int) $value->sort_order,
                    'configured' => true,
                ];
                $selected[$optionId] = (int) $value->id;
            }
            $rows[] = [
                'id' => (int) $variant->id, 'sku' => $variant->sku,
                'price' => (int) ($variant->price_override ?? $product->new_price),
                'old_price' => (int) ($variant->old_price_override ?? $product->old_price ?? 0),
                // Sellable quantity remains reservation-aware for the cart/checkout guard.
                'stock' => StorefrontDeferredStock::availableForVariant((int) $variant->id, (int) $variant->stock),
                // Public product detail shows real on-hand stock until Confirmed.
                'physical_stock' => max(0, (int) $variant->stock),
                'selected' => $selected,
            ];
        }
        // Option Values is the shared admin vocabulary, not a priced SKU table.
        // Display all currently active values of each dimension that belongs to
        // this product, while keeping the purchasable variant rows authoritative.
        // A newly added value is visible, but it will not invent stock or price.
        if ($options !== []) {
            $assigned = ProductOption::query()->whereIn('id', array_keys($options))
                ->where('is_active', true)
                ->with(['values' => static fn ($query) => $query->where('is_active', true)])
                ->get();
            foreach ($assigned as $option) {
                $values = [];
                foreach ($option->values as $value) {
                    $values[(int) $value->id] = [
                        'id' => (int) $value->id,
                        'label' => $value->label,
                        'swatch' => $value->swatch_hex,
                        'sort_order' => (int) $value->sort_order,
                        'configured' => collect($rows)->contains(
                            static fn (array $row): bool =>
                                ($row['selected'][(int) $option->id] ?? null) === (int) $value->id
                        ),
                    ];
                }
                $options[(int) $option->id]['values'] = $values;
            }
        }
        uasort($options, static fn ($a, $b) => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
        foreach ($options as &$option) {
            uasort($option['values'], static fn ($a, $b) => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
            $option['values'] = array_values($option['values']);
        }
        unset($option);
        return ['options' => array_values($options), 'variants' => $rows];
    }
}
