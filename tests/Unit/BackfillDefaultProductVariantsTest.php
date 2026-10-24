<?php

namespace Tests\Unit;

use App\Console\Commands\BackfillDefaultProductVariants;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BackfillDefaultProductVariantsTest extends TestCase
{
    public function test_fallback_legacy_sku_is_deterministic_and_scoped_to_product_id(): void
    {
        self::assertSame('LEGACY-42', BackfillDefaultProductVariants::legacySku(42));
        self::assertSame('LEGACY-42', BackfillDefaultProductVariants::legacySku(42));
        self::assertNotSame(
            BackfillDefaultProductVariants::legacySku(42),
            BackfillDefaultProductVariants::legacySku(43)
        );
    }

    public function test_legacy_sku_rejects_invalid_product_id(): void
    {
        $this->expectException(RuntimeException::class);
        BackfillDefaultProductVariants::legacySku(0);
    }
}
