<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SafeCsvCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Pure test: no Laravel app, database, session, or file writes. */
final class SafeCsvCellTest extends TestCase
{
    public static function cells(): array
    {
        return [
            'formula' => ['=1+2', "'=1+2"],
            'positive formula' => ['+SUM(A1)', "'+SUM(A1)"],
            'negative formula' => ['-2+3', "'-2+3"],
            'import function' => ['@IMPORTDATA(url)', "'@IMPORTDATA(url)"],
            'leading tab' => ["\t=1+2", "'\t=1+2"],
            'leading spaces' => ['   =1+2', "'   =1+2"],
            'negative money' => ['-30.25', '-30.25'],
            'decimal money' => ['30.25', '30.25'],
            'text' => ['Customer', 'Customer'],
            'boolean' => [true, 'Yes'],
            'null' => [null, ''],
        ];
    }

    #[DataProvider('cells')]
    public function test_text_is_inert_when_exported_to_csv(mixed $input, string $expected): void
    {
        self::assertSame($expected, SafeCsvCell::stringify($input));
    }
}
