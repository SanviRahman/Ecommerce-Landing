<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

/**
 * Exact non-negative BDT arithmetic. Public database values are decimal BDT;
 * internal calculations use integer poisha, never binary floats.
 */
final class ExactBdt
{
    // Fits DECIMAL(14,2) and a signed 64-bit PHP integer.
    public const MAX_MINOR_UNITS = 99_999_999_999_999;

    public static function fromDecimal(int|string $amount): int
    {
        if (! is_int($amount) && ! is_string($amount)) {
            throw new InvalidArgumentException('Money must be an integer or decimal string.');
        }

        $literal = (string) $amount;

        if (! preg_match('/\A(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?\z/D', $literal, $matches)) {
            throw new InvalidArgumentException('Use an unsigned BDT amount with at most two decimal places.');
        }

        $whole = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '', 2, '0', STR_PAD_RIGHT);
        $minor = $whole * 100 + (int) $fraction;

        if ($minor > self::MAX_MINOR_UNITS) {
            throw new OverflowException('Money exceeds DECIMAL(14,2).');
        }

        return $minor;
    }

    public static function toDecimal(int $minorUnits): string
    {
        self::assertWithinRange($minorUnits);

        return intdiv($minorUnits, 100) . '.' . str_pad((string) ($minorUnits % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function multiply(int $unitMinor, int $quantity): int
    {
        self::assertWithinRange($unitMinor);
        if ($quantity < 0) {
            throw new InvalidArgumentException('A negative quantity is not permitted.');
        }
        if ($quantity !== 0 && $unitMinor > intdiv(self::MAX_MINOR_UNITS, $quantity)) {
            throw new OverflowException('Line total exceeds DECIMAL(14,2).');
        }

        return $unitMinor * $quantity;
    }

    public static function add(int ...$amounts): int
    {
        $total = 0;
        foreach ($amounts as $amount) {
            self::assertWithinRange($amount);
            if ($total > self::MAX_MINOR_UNITS - $amount) {
                throw new OverflowException('Sum exceeds DECIMAL(14,2).');
            }
            $total += $amount;
        }

        return $total;
    }

    public static function subtract(int $amount, int $discount): int
    {
        self::assertWithinRange($amount);
        self::assertWithinRange($discount);
        if ($discount > $amount) {
            throw new InvalidArgumentException('Discount cannot exceed the amount.');
        }

        return $amount - $discount;
    }

    private static function assertWithinRange(int $minorUnits): void
    {
        if ($minorUnits < 0 || $minorUnits > self::MAX_MINOR_UNITS) {
            throw new InvalidArgumentException('Money amount out of range.');
        }
    }
}
