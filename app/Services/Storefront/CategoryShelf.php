<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use Illuminate\Support\Collection;

/** Resolves dynamic category shelves using the same active product rules as the public catalog. */
final class CategoryShelf
{
    public function __construct(private readonly PublicCatalog $catalog) {}

    public function productsFor(array $section): Collection
    {
        if (($section['type'] ?? '') !== 'category_products' || ! ($section['is_enabled'] ?? false)) {
            return collect();
        }
        $sourceId = (int) ($section['category_id'] ?? 0);
        if ($sourceId < 1) return collect();
        $category = $this->catalog->eligibleCategories()->where('id', $sourceId)->first();
        if (! $category) return collect();
        $ids = [$sourceId];
        if ((bool) ($section['include_descendants'] ?? false) && $category->parent_id === null) {
            $children = $this->catalog->eligibleCategories()->where('parent_id', $sourceId)->pluck('id')->all();
            $ids = array_merge($ids, $children);
        }
        $sort = (string) ($section['product_sort'] ?? 'newest');
        if (! in_array($sort, ['newest', 'name', 'price_asc', 'price_desc'], true)) $sort = 'newest';
        $limit = max(1, min(24, (int) ($section['product_limit'] ?? 8)));
        return $this->catalog->sortedProducts($this->catalog->eligibleProducts()->whereIn('products.category_id', $ids), $sort)
            ->limit($limit)->get();
    }
}
