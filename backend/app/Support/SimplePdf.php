<?php

namespace App\Support;

/** Text-only A4 PDFs: fixed standard fonts, escaped text, no HTML or remote assets. */
class SimplePdf
{
    public function render(string $title, array $lines): string
    {
        $wrapped = [];
        foreach ($lines as $line) {
            $text = str_replace("\t", '    ', (string) $line);
            foreach (preg_split('/\R/u', $text) ?: [''] as $paragraph) {
                $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $paragraph);
                $encoded = preg_replace('/[\x00-\x1f\x7f]/', '', $encoded === false ? '' : $encoded);
                array_push($wrapped, ...explode("\n", wordwrap($encoded, 92, "\n", true)));
            }
        }
        $pages = array_chunk($wrapped ?: [''], 45);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        foreach ($pages as $index => $page) {
            $pageId = 5 + $index * 2;
            $streamId = $pageId + 1;
            $kids[] = $pageId.' 0 R';
            $safeTitle = $this->escape(iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $title) ?: 'eRepair document');
            $stream = "0.06 0.12 0.25 rg\n40 755 515 46 re f\nBT /F2 17 Tf 1 1 1 rg 54 774 Td (eRepair) Tj ET\n";
            $stream .= 'BT /F2 14 Tf 0.06 0.12 0.25 rg 44 728 Td ('.$safeTitle.") Tj ET\n";
            $stream .= "BT /F1 10 Tf 0.12 0.16 0.22 rg 44 700 Td 14 TL\n";
            foreach ($page as $line) {
                $stream .= '('.$this->escape($line).") Tj T*\n";
            }
            $stream .= "ET\nBT /F1 9 Tf 0.35 0.4 0.45 rg 44 40 Td (eRepair | Page ".($index + 1).' of '.count($pages).") Tj ET\n";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$streamId.' 0 R >>';
            $objects[$streamId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
        }
        $objects[2] = '<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ', $kids).'] >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
    }
}
