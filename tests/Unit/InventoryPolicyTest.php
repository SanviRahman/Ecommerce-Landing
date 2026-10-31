<?php

namespace Tests\Unit;

use App\Services\Inventory\InventoryPolicy;
use DomainException;
use PHPUnit\Framework\TestCase;

class InventoryPolicyTest extends TestCase
{
    public function test_exact_stock_is_purchasable(): void
    {
        InventoryPolicy::assertPurchasable(1, 1);
        $this->assertSame([2 => 2, 9 => 1], InventoryPolicy::canonicalize([9 => 1, 2 => 2]));
    }

    public function test_last_unit_cannot_be_oversold(): void
    {
        $this->expectException(DomainException::class);
        InventoryPolicy::assertPurchasable(1, 2);
    }

    public function test_courier_order_cannot_auto_restock(): void
    {
        $this->expectException(DomainException::class);
        InventoryPolicy::assertCanRelease('cancelled', false, false, true);
    }
}
