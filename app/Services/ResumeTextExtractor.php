<?php
namespace App\Services;

use Smalot\PdfParser\Parser;
use Illuminate\Http\UploadedFile;
use Exception;

class ResumeTextExtractor
{
    public function extractText(UploadedFile $file): string
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($file->getPathname());
            $text = $pdf->getText();
            $cleanedText = preg_replace('/\s+/', ' ', $text);

            return trim($cleanedText);
        } catch (Exception $e) {
            return '';
        }
    }
}