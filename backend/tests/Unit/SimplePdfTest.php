<?php

namespace Tests\Unit;

use App\Support\SimplePdf;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class SimplePdfTest extends TestCase
{
    public function test_multipage_pdf_has_valid_offsets_and_escapes_text_as_data(): void
    {
        $pdf = (new SimplePdf)->render('Repair document', array_merge(['Customer (sample) \\ device', 'José: screen repaired'], array_fill(0, 95, 'A complete repair history line.')));
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        preg_match('/startxref\n(\d+)\n/', $pdf, $match);
        $this->assertStringStartsWith('xref', substr($pdf, (int) $match[1]));
        $this->assertStringContainsString('/Count 3', $pdf);
        $binary = (new ExecutableFinder)->find('pdftotext');
        if ($binary) {
            $file = tempnam(sys_get_temp_dir(), 'erepair-pdf-');
            try {
                file_put_contents($file, $pdf);
                $process = new Process([$binary, '-enc', 'UTF-8', '-layout', $file, '-']);
                $process->mustRun();
                $text = $process->getOutput();
                $this->assertStringContainsString('Customer (sample)', $text);
                $this->assertStringContainsString('José: screen repaired', $text);
                $this->assertStringContainsString('Page 3 of 3', $text);
            } finally {
                unlink($file);
            }
        }
    }
}
