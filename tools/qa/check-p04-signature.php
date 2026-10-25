<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Support/VariantSignature.php';

use App\Support\VariantSignature;

$checks = 0;
function checkP04(bool $okay, string $name): void
{
    global $checks;
    if (! $okay) {
        fwrite(STDERR, "FAIL: $name\n");
        exit(1);
    }
    $checks++;
    echo "PASS $name\n";
}
function rejectsP04(array $input): bool
{
    try {
        VariantSignature::fromOptionValues($input);
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
}
checkP04(VariantSignature::fromOptionValues([7 => 22, 1 => 8]) === '1:8|7:22', 'Signature is canonical by option ID');
checkP04(VariantSignature::fromOptionValues([1 => 8, 7 => 22]) === '1:8|7:22', 'Selection order does not change identity');
checkP04(rejectsP04([]), 'Empty option set is refused');
checkP04(rejectsP04([0 => 3]), 'Option zero is refused');
checkP04(rejectsP04([1 => 0]), 'Value zero is refused');
checkP04(rejectsP04([1 => '2']), 'String value IDs are refused');
checkP04(rejectsP04([1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6]), 'More than four dimensions refused');
checkP04(VariantSignature::fromOptionValues([1 => 2]) === '1:2', 'Single option identity preserved');
echo "RESULT: $checks/$checks signature checks PASS (pure PHP).\n";
