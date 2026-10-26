<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Read-only P07 browse contract. Checkout and legacy campaigns remain independent. */
final class PublicCatalog
{
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
        return Product::query()->where('status', true)
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

    /** No user-provided SQL fragments. Stable ties by products.id. */
    public function sortedProducts(Builder $query, string $sort): Builder
    {
        $query->select('products.*')->selectRaw('(' . $this->priceExpression() . ') AS catalog_price')
            ->with(['category', 'media', 'variants.optionValues.option']);
        return match ($sort) {
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
            ->where('product_id', $product->id)->pluck('product_option_id')->map('intval')->all();
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
        return $product->has_variants
            ? (int) $this->eligibleVariants($product)->sum('stock')
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
                ];
                $selected[$optionId] = (int) $value->id;
            }
            $rows[] = [
                'id' => (int) $variant->id, 'sku' => $variant->sku,
                'price' => (int) ($variant->price_override ?? $product->new_price),
                'old_price' => (int) ($variant->old_price_override ?? $product->old_price ?? 0),
                'stock' => (int) $variant->stock, 'selected' => $selected,
            ];
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
