<?php

namespace App\Services\Rag;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser;

class TextExtractionService
{
    public function extractFromUploadedFile(UploadedFile $file): string
    {
        return $this->extractFromPath(
            $file->getRealPath(),
            $file->getMimeType() ?? 'application/octet-stream'
        );
    }

    public function extractFromPath(string $path, string $mimeType): string
    {
        if (! is_readable($path)) {
            throw new RuntimeException("File is not readable: {$path}");
        }

        $mime = strtolower($mimeType);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match (true) {
            str_contains($mime, 'pdf'), $extension === 'pdf' => $this->extractPdf($path),
            str_contains($mime, 'wordprocessingml'), $extension === 'docx' => $this->extractDocx($path),
            str_contains($mime, 'msword'), $extension === 'doc' => throw new RuntimeException(
                'Legacy .doc files are not supported. Export Google Docs as .docx, .pdf, or .txt.'
            ),
            str_contains($mime, 'text'), in_array($extension, ['txt', 'md', 'markdown'], true) => $this->extractPlainText($path),
            default => throw new RuntimeException("Unsupported file type: {$mimeType}"),
        };
    }

    public function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    protected function extractPdf(string $path): string
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($path);

        return $this->normalize($pdf->getText());
    }

    protected function extractDocx(string $path): string
    {
        $phpWord = IOFactory::load($path);

        $sections = [];

        foreach ($phpWord->getSections() as $section) {
            $sections[] = $this->extractElements($section->getElements());
        }

        return $this->normalize(implode("\n\n", array_filter($sections)));
    }

    protected function extractElements(iterable $elements): string
    {
        $parts = [];

        foreach ($elements as $element) {
            if ($element instanceof AbstractContainer) {
                $parts[] = $this->extractElements($element->getElements());
                continue;
            }

            if (method_exists($element, 'getText')) {
                $text = $element->getText();

                if (is_string($text) && $text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode(' ', array_filter($parts)));
    }

    protected function extractPlainText(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read plain text file: {$path}");
        }

        return $this->normalize($contents);
    }
}
