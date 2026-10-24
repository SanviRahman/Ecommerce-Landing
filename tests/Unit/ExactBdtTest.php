<?php

namespace Tests\Unit;

use App\Support\ExactBdt;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class ExactBdtTest extends TestCase
{
    public function test_discount_is_exact_without_floats(): void
    {
        $result = ExactBdt::subtract(ExactBdt::fromDecimal('1000.00'), ExactBdt::fromDecimal('0.50'));
        $this->assertSame('999.50', ExactBdt::toDecimal($result));
    }

    public function test_10_plus_20_poisha_is_30_poisha(): void
    {
        $this->assertSame('0.30', ExactBdt::toDecimal(ExactBdt::add(
            ExactBdt::fromDecimal('0.10'),
            ExactBdt::fromDecimal('0.20')
        )));
    }

    public function test_quantity_and_limit_enforcement(): void
    {
        $this->assertSame('299.97', ExactBdt::toDecimal(ExactBdt::multiply(ExactBdt::fromDecimal('99.99'), 3)));
        $this->expectException(OverflowException::class);
        ExactBdt::multiply(ExactBdt::MAX_MINOR_UNITS, 2);
    }

    public function test_fractional_overprecision_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ExactBdt::fromDecimal('1.005');
    }
}
