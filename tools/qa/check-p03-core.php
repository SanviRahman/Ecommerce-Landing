<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Support/ExactBdt.php';
require __DIR__ . '/../../app/Services/Inventory/InventoryPolicy.php';

use App\Services\Inventory\InventoryPolicy;
use App\Support\ExactBdt;

$passed = 0;
$failed = 0;

function check(string $name, callable $callback): void
{
    global $passed, $failed;
    try {
        if ($callback() !== true) {
            throw new RuntimeException('Assertion was not true');
        }
        $passed++;
        echo "PASS {$name}\n";
    } catch (Throwable $error) {
        $failed++;
        echo "FAIL {$name}: {$error->getMessage()}\n";
    }
}
function failsWith(string $type, callable $callback): bool
{
    try {
        $callback();
        return false;
    } catch (Throwable $exception) {
        return $exception instanceof $type;
    }
}

check('Exact BDT integer input', fn () => ExactBdt::fromDecimal(1000) === 100000);
check('Exact BDT fractional input', fn () => ExactBdt::fromDecimal('1000.50') === 100050);
check('Exact BDT 1-digit fractional input', fn () => ExactBdt::fromDecimal('0.5') === 50);
check('Exact BDT correct zero formatting', fn () => ExactBdt::toDecimal(0) === '0.00');
check('Exact BDT correct precision', fn () => ExactBdt::toDecimal(100050) === '1000.50');
check('Discount exactly 0.50 from 1000', fn () => ExactBdt::toDecimal(ExactBdt::subtract(ExactBdt::fromDecimal('1000.00'), ExactBdt::fromDecimal('0.50'))) === '999.50');
check('Float-safe 0.10 + 0.20', fn () => ExactBdt::toDecimal(ExactBdt::add(ExactBdt::fromDecimal('0.10'), ExactBdt::fromDecimal('0.20'))) === '0.30');
check('Quantity pricing 99.99 * 3', fn () => ExactBdt::toDecimal(ExactBdt::multiply(ExactBdt::fromDecimal('99.99'), 3)) === '299.97');
check('Maximum DECIMAL(14,2)', fn () => ExactBdt::toDecimal(ExactBdt::fromDecimal('999999999999.99')) === '999999999999.99');
check('Money rejects floating input', fn () => failsWith(TypeError::class, fn () => ExactBdt::fromDecimal(0.1)));
check('Money rejects scientific notation', fn () => failsWith(InvalidArgumentException::class, fn () => ExactBdt::fromDecimal('1e2')));
check('Money rejects more than 2 decimals', fn () => failsWith(InvalidArgumentException::class, fn () => ExactBdt::fromDecimal('12.345')));
check('Money rejects negative amounts', fn () => failsWith(InvalidArgumentException::class, fn () => ExactBdt::fromDecimal('-12')));
check('Money rejects excess leading zeroes', fn () => failsWith(InvalidArgumentException::class, fn () => ExactBdt::fromDecimal('001')));
check('Money rejects multiplication overflow', fn () => failsWith(OverflowException::class, fn () => ExactBdt::multiply(ExactBdt::fromDecimal('999999999999.99'), 2)));
check('Money rejects excessive discount', fn () => failsWith(InvalidArgumentException::class, fn () => ExactBdt::subtract(100, 101)));
check('Inventory permits exactly available stock', fn () => (InventoryPolicy::assertPurchasable(1, 1) ?? true) === true);
check('Inventory rejects oversell', fn () => failsWith(DomainException::class, fn () => InventoryPolicy::assertPurchasable(1, 2)));
check('Inventory rejects zero quantity', fn () => failsWith(InvalidArgumentException::class, fn () => InventoryPolicy::assertPurchasable(10, 0)));
check('Inventory rejects over-limit quantity', fn () => failsWith(InvalidArgumentException::class, fn () => InventoryPolicy::assertPurchasable(2000, 1001)));
check('Cancellation safely releasable before dispatch', fn () => (InventoryPolicy::assertCanRelease('cancelled', false, false, false) ?? true) === true);
check('Cancellation rejects delivered order', fn () => failsWith(DomainException::class, fn () => InventoryPolicy::assertCanRelease('cancelled', false, true, false)));
check('Cancellation rejects courier consignment', fn () => failsWith(DomainException::class, fn () => InventoryPolicy::assertCanRelease('cancelled', false, false, true)));
check('Cancellation rejects unauthorized processing state', fn () => failsWith(DomainException::class, fn () => InventoryPolicy::assertCanRelease('processing', false, false, false)));
check('Variant lock IDs sorted', fn () => array_keys(InventoryPolicy::canonicalize([9 => 1, 2 => 3])) === [2, 9]);
check('Variant quantity invalid', fn () => failsWith(InvalidArgumentException::class, fn () => InventoryPolicy::canonicalize([2 => -1])));

echo "Result: {$passed} passed; {$failed} failed (pure PHP, NO MySQL)\n";
exit($failed === 0 ? 0 : 1);
