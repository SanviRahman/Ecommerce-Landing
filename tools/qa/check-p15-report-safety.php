<?php

declare(strict_types=1);

/** Source/pure-PHP safety check: no Laravel, DB, .env or external calls. */
$root = dirname(__DIR__, 2);
$checks = 0;
$passes = 0;
$assert = static function (string $name, bool $condition) use (&$checks, &$passes): void {
    $checks++;
    if (! $condition) {
        fwrite(STDERR, "FAIL {$name}\n");
        return;
    }
    $passes++;
    echo "PASS {$name}\n";
};

$controller = file_get_contents($root . '/app/Http/Controllers/Admin/ReportController.php');
$apache = file_get_contents($root . '/public/.htaccess');
$assert('CSV/PDF exports never write to web-public storage', ! str_contains($controller, "Storage::disk('public')->put("));
$assert('CSV exports target private local disk', str_contains($controller, "Storage::disk('local')->put(")
    && str_contains($controller, "'file_disk' => 'local'"));
$assert('Optional report title has a safe fallback', str_contains($controller, "\$validated['title'] ?? null"));
$assert('CSV report fields use a centralized safe cell serializer',
    str_contains($controller, 'SafeCsvCell::stringify') && ! str_contains($controller, 'fputcsv($handle, ['));
$assert('Apache denies direct access to old public reports',
    str_contains($apache, 'storage/reports') && str_contains($apache, '[F,L,NC]'));
$assert('Existing files remain available via authorized download endpoint',
    str_contains($controller, 'public function download(ReportExport $report)')
    && str_contains($controller, 'Storage::disk($report->file_disk)'));

$helper = $root . '/app/Support/SafeCsvCell.php';
if (! is_file($helper)) {
    $assert('Safe CSV helper is present', false);
} else {
    require_once $helper;
    $assert('Safe CSV helper is present', true);
    $cases = [
        ['=1+2', "'=1+2"], ['+SUM(A1)', "'+SUM(A1)"], ['-2+3', "'-2+3"],
        ['@IMPORTDATA(url)', "'@IMPORTDATA(url)"],
        ["\t=HYPERLINK(\"https://example.test\")", "'\t=HYPERLINK(\"https://example.test\")"],
        ['    =cmd', "'    =cmd"], ['-3.25', '-3.25'],
        ['500', '500'], [500, '500'], [true, 'Yes'], [false, 'No'],
        [null, ''], ['Customer Name', 'Customer Name'],
    ];
    foreach ($cases as [$input, $expected]) {
        $assert('Safe CSV data-cell rendering: ' . get_debug_type($input),
            \App\Support\SafeCsvCell::stringify($input) === $expected);
    }
}
echo "RESULT: {$passes}/{$checks} P15 private-report/CSV checks " . ($passes === $checks ? 'PASS' : 'FAIL') . " (offline).\n";
exit($passes === $checks ? 0 : 1);
