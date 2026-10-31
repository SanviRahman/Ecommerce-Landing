<?php

namespace App\Services\Inventory;

use DomainException;
use InvalidArgumentException;

/**
 * Pure mapping rule for the P03 simple-product writer. No DB calls here.
 * Every persisted item must select the one active, default, un-deleted variant
 * belonging to its product. Never infer option combinations or fallback SKUs.
 */
final class DefaultVariantMapping
{
    /**
     * @param array<int, array{product_id:int}> $itemsById
     * @param list<array{id:int,product_id:int,option_signature:string,is_default:bool,is_active:bool,deleted_at:mixed}> $variants
     * @return array<int,int> order_item_id => variant_id
     */
    public static function resolve(array $itemsById, array $variants): array
    {
        if ($itemsById === []) {
            throw new InvalidArgumentException('The persisted order has no items.');
        }

        $requiredProducts = [];
        foreach ($itemsById as $itemId => $item) {
            if (! is_int($itemId) || $itemId < 1 || ! is_int($item['product_id'] ?? null)
                || $item['product_id'] < 1) {
                throw new InvalidArgumentException('Persisted item/product identifiers must be positive integers.');
            }
            $requiredProducts[$item['product_id']] = true;
        }

        $defaultsByProduct = [];
        foreach ($variants as $variant) {
            $productId = $variant['product_id'] ?? null;
            $variantId = $variant['id'] ?? null;
            if (! is_int($productId) || $productId < 1 || ! is_int($variantId) || $variantId < 1) {
                throw new InvalidArgumentException('Invalid default variant identity.');
            }
            if (! isset($requiredProducts[$productId])) {
                throw new DomainException('The variant lookup returned an unrelated product.');
            }
            if (($variant['option_signature'] ?? '') !== 'default'
                || ($variant['is_default'] ?? false) !== true
                || ($variant['is_active'] ?? false) !== true
                || ($variant['deleted_at'] ?? null) !== null) {
                throw new DomainException('A simple product does not have an active default variant.');
            }
            if (isset($defaultsByProduct[$productId])) {
                throw new DomainException('Multiple default variants exist for the same product.');
            }
            $defaultsByProduct[$productId] = $variantId;
        }

        if (count($defaultsByProduct) !== count($requiredProducts)) {
            throw new DomainException('Reconcile every ordered product to one active default variant first.');
        }

        $resolved = [];
        foreach ($itemsById as $itemId => $item) {
            $resolved[$itemId] = $defaultsByProduct[$item['product_id']];
        }
        ksort($resolved, SORT_NUMERIC);

        return $resolved;
    }
}
