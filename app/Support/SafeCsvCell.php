<?php

declare(strict_types=1);

namespace App\Support;

/** Plain-text CSV cells; escaping protects spreadsheet viewers, not SQL/HTML. */
final class SafeCsvCell
{
    public static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        }

        $text = (string) $value;

        // Preserve genuine numeric amounts, including negative BDT values.
        // Quoted CSV fields alone do NOT prevent formula execution in Excel.
        if (is_numeric($text)) {
            return $text;
        }

        // Also cover leading spaces, TAB and CR often used to bypass CSV checks.
        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $text) === 1) {
            return "'" . $text;
        }

        return $text;
    }
}
