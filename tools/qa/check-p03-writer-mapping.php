<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Services/Inventory/DefaultVariantMapping.php';

use App\Services\Inventory\DefaultVariantMapping;

$passed = 0;
$failed = 0;

function check(string $name, callable $callback, ?string $exceptionClass = null): void
{
    global $passed, $failed;
    try {
        $result = $callback();
        if ($exceptionClass !== null) {
            throw new RuntimeException('Expected ' . $exceptionClass . ' but returned normally.');
        }
        if ($result !== true) {
            throw new RuntimeException('Result differs from expectation.');
        }
        echo "PASS {$name}\n";
        $passed++;
    } catch (Throwable $exception) {
        if ($exceptionClass !== null && is_a($exception, $exceptionClass)) {
            echo "PASS {$name}\n";
            $passed++;
            return;
        }
        echo "FAIL {$name}: {$exception->getMessage()}\n";
        $failed++;
    }
}

$default = static fn (int $id, int $productId) => [
    'id' => $id,
    'product_id' => $productId,
    'option_signature' => 'default',
    'is_default' => true,
    'is_active' => true,
    'deleted_at' => null,
];

check('Single valid simple-product mapping', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 9]], [$default(17, 9)]) === [4 => 17]);
check('Same product in multiple items resolves same variant', static fn () => DefaultVariantMapping::resolve([8 => ['product_id' => 9], 3 => ['product_id' => 9]], [$default(17, 9)]) === [3 => 17, 8 => 17]);
check('Multiple products have sorted item mappings', static fn () => DefaultVariantMapping::resolve([9 => ['product_id' => 6], 1 => ['product_id' => 4]], [$default(62, 6), $default(42, 4)]) === [1 => 42, 9 => 62]);
check('No order items fails closed', static fn () => DefaultVariantMapping::resolve([], []), InvalidArgumentException::class);
check('Item ID zero fails closed', static fn () => DefaultVariantMapping::resolve([0 => ['product_id' => 9]], [$default(17, 9)]), InvalidArgumentException::class);
check('Item ID string fails closed', static fn () => DefaultVariantMapping::resolve(['unknown' => ['product_id' => 9]], [$default(17, 9)]), InvalidArgumentException::class);
check('Product ID zero fails closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 0]], [$default(17, 9)]), InvalidArgumentException::class);
check('Product ID string fails closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => '9']], [$default(17, 9)]), InvalidArgumentException::class);
check('Missing default variant fails closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 9]], []), DomainException::class);
check('Duplicate default variants fail closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 9]], [$default(17, 9), $default(18, 9)]), DomainException::class);
check('Foreign product variant fails closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 9]], [$default(17, 5)]), DomainException::class);
check('Zero variant ID fails closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 9]], [$default(0, 9)]), InvalidArgumentException::class);
check('Inactive default variant fails closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 9]], [array_replace($default(17, 9), ['is_active' => false])]), DomainException::class);
check('Deleted default variant fails closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 9]], [array_replace($default(17, 9), ['deleted_at' => '2026-01-01'])]), DomainException::class);
check('Incorrect signature fails closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 9]], [array_replace($default(17, 9), ['option_signature' => 'color:red'])]), DomainException::class);
check('Nondefault variant fails closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 9]], [array_replace($default(17, 9), ['is_default' => false])]), DomainException::class);
check('Invalid empty variant identity fails closed', static fn () => DefaultVariantMapping::resolve([4 => ['product_id' => 9]], [array_replace($default(17, 9), ['product_id' => 0])]), InvalidArgumentException::class);

printf("Result: %d passed, %d failed (pure PHP, no DB).\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
