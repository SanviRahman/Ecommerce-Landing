<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\PublicCatalog;
use Illuminate\Support\Collection;

/** Campaign-owned variant matrices and server-authoritative BDT prices. */
final class CampaignVariantCatalog
{
    public function __construct(private readonly PublicCatalog $catalog) {}

    public static function resolvedUnitPrice(int $base, int $campaignPrice, ?int $skuPrice, bool $hasVariants): int
    {
        // Explicit SKU price wins. Never multiply unit prices by pack size.
        return $hasVariants && $skuPrice !== null
            ? $skuPrice
            : ($campaignPrice > 0 ? $campaignPrice : $base);
    }

    public function unitPrice(Product $product, ?ProductVariant $variant = null, ?int $campaignPrice = null): int
    {
        $campaignPrice ??= (int) ($product->pivot?->campaign_price ?? 0);
        return self::resolvedUnitPrice(
            (int) $product->new_price,
            $campaignPrice,
            $variant !== null ? $variant->price_override : null,
            (bool) $product->has_variants
        );
    }

    public function matrix(Product $product): array
    {
        if (app(\App\Services\Catalog\OptionWiseSelections::class)->configured($product)) {
            return app(\App\Services\Catalog\OptionWiseSelections::class)->matrix($product);
        }
        if (! $product->has_variants) {
            return ['options' => [], 'variants' => []];
        }
        $selection = $this->catalog->selectionData($product);
        $byId = $this->catalog->eligibleVariants($product)->keyBy('id');
        foreach ($selection['variants'] as &$row) {
            $variant = $byId->get((int) $row['id']);
            if ($variant) {
                $row['price'] = $this->unitPrice($product, $variant);
            }
        }
        unset($row);
        return $selection;
    }

    public function forProducts(Collection $products): array
    {
        $matrices = [];
        foreach ($products as $product) {
            if ($product->has_variants || app(\App\Services\Catalog\OptionWiseSelections::class)->configured($product)) {
                $matrices[(int) $product->id] = $this->matrix($product);
            }
        }
        return $matrices;
    }
}
