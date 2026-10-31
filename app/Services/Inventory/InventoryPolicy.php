<?php

namespace App\Services\Inventory;

use DomainException;
use InvalidArgumentException;

/** Business rules shared by the staged P03 allocation and release operations. */
final class InventoryPolicy
{
    public static function assertPurchasable(int $available, int $requested): void
    {
        if ($requested < 1 || $requested > 1000) {
            throw new InvalidArgumentException('Order quantity must be between 1 and 1000.');
        }
        if ($available < $requested) {
            throw new DomainException('Insufficient stock for this variant.');
        }
    }

    public static function assertCanRelease(
        string $status,
        bool $wasShipped,
        bool $wasDelivered,
        bool $hasCourierConsignment
    ): void {
        if (! in_array($status, ['cancelled', 'canceled'], true)) {
            throw new DomainException('Stock release requires an explicitly cancelled order.');
        }
        if ($wasShipped || $wasDelivered || $hasCourierConsignment) {
            throw new DomainException('Shipped/courier orders require a separately approved physical return.');
        }
    }

    /**
     * @param array<int,int> $quantitiesByVariantId
     * @return array<int,int>
     */
    public static function canonicalize(array $quantitiesByVariantId): array
    {
        $normalized = [];
        foreach ($quantitiesByVariantId as $variantId => $quantity) {
            if (! is_int($variantId) || $variantId < 1 || ! is_int($quantity)) {
                throw new InvalidArgumentException('Variant ID and quantity must be positive integers.');
            }
            self::assertPurchasable(1000, $quantity);
            $normalized[$variantId] = $quantity;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }
}
