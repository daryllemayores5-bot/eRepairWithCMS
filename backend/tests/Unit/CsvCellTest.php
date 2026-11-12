<?php

namespace Tests\Unit;

use App\Support\CsvCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvCellTest extends TestCase
{
    #[DataProvider('cells')]
    public function test_spreadsheet_text_is_protected_without_changing_trusted_numbers(mixed $input, mixed $expected): void
    {
        $this->assertSame($expected, CsvCell::forSpreadsheet($input));
    }

    public static function cells(): array
    {
        return [
            'name' => ['Ana Reyes', 'Ana Reyes'],
            'comma and quotes' => ['Santos, "Miguel"', 'Santos, "Miguel"'],
            'zero' => [0, 0],
            'rating' => [4.75, 4.75],
            'trusted numeric negative' => [-1, -1],
            'empty' => ['', ''],
            'null' => [null, null],
            'equals' => ['=1+1', "\t=1+1"],
            'plus' => ['+1', "\t+1"],
            'minus text' => ['-1', "\t-1"],
            'at sign' => ['@SUM(1,2)', "\t@SUM(1,2)"],
            'leading spaces' => ['  =1+1', "\t  =1+1"],
            'leading tab' => ["\t=1+1", "\t\t=1+1"],
            'leading newline' => ["\r\n=1+1", "\t\r\n=1+1"],
            'nonbreaking space' => ["\u{00A0}=1+1", "\t\u{00A0}=1+1"],
            'byte order mark' => ["\u{FEFF}@SUM(1,2)", "\t\u{FEFF}@SUM(1,2)"],
            'fullwidth equals' => ["\u{FF1D}1+1", "\t\u{FF1D}1+1"],
            'fullwidth plus' => ["\u{FF0B}1", "\t\u{FF0B}1"],
            'fullwidth minus' => ["\u{FF0D}1", "\t\u{FF0D}1"],
            'fullwidth at' => ["\u{FF20}SUM(1,2)", "\t\u{FF20}SUM(1,2)"],
        ];
    }

    public function test_csv_writer_preserves_cells_containing_quotes_separators_and_newlines(): void
    {
        $values = ['Santos, "Miguel"', "=1+1\r\nmore text", 'Normal",=1+1', 4.75];
        $safe = array_map([CsvCell::class, 'forSpreadsheet'], $values);
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $safe, ',', '"', '');
        rewind($stream);
        $read = fgetcsv($stream, null, ',', '"', '');
        $this->assertSame(array_map('strval', $safe), $read);
        $this->assertFalse(fgetcsv($stream, null, ',', '"', ''));
        fclose($stream);
    }
}
