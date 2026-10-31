<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Immutable, ordering-independent identity for a sellable option combination.
 * IDs (not editable display labels) are used so renaming Color/Size is safe.
 */
final class VariantSignature
{
    public static function fromOptionValues(array $valueIdsByOptionId): string
    {
        if ($valueIdsByOptionId === [] || count($valueIdsByOptionId) > 4) {
            throw new InvalidArgumentException('Select between one and four option values.');
        }

        $normalized = [];
        foreach ($valueIdsByOptionId as $optionId => $valueId) {
            if (! ctype_digit((string) $optionId) || (int) $optionId < 1
                || ! is_int($valueId) || $valueId < 1) {
                throw new InvalidArgumentException('Option/value IDs must be positive integers.');
            }
            $normalized[(int) $optionId] = $valueId;
        }
        if (count($normalized) !== count($valueIdsByOptionId)) {
            throw new InvalidArgumentException('Duplicate option identity is not allowed.');
        }
        ksort($normalized, SORT_NUMERIC);

        $signature = implode('|', array_map(
            static fn ($optionId, $valueId) => $optionId . ':' . $valueId,
            array_keys($normalized), array_values($normalized)
        ));
        if (strlen($signature) > 191) {
            throw new InvalidArgumentException('Option identity is too long.');
        }

        return $signature;
    }
}
