<?php

namespace App\Support;

class CsvCell
{
    /**
     * Protect spreadsheet-facing text, then pass the result to fputcsv.
     * The leading tab is intentional exported data, not a display-only escape.
     */
    public static function forSpreadsheet(string|int|float|null $value): string|int|float|null
    {
        if (! is_string($value)) {
            return $value;
        }

        $candidate = preg_replace('/\A[\s\p{Z}\x{FEFF}\x00-\x1F]*/u', '', $value) ?? $value;
        $formula = '/\A[=+@\-\x{FF1D}\x{FF0B}\x{FF0D}\x{FF20}]/u';

        return preg_match($formula, $candidate) === 1 ? "\t".$value : $value;
    }
}
