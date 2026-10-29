<?php

namespace App\Services\Rag;

class ChunkTextService
{
    public function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $maxChars = max(200, (int) config('rag.chunk.max_chars', 1200));
        $overlap = max(0, min((int) config('rag.chunk.overlap', 200), $maxChars - 1));
        $minChars = max(0, (int) config('rag.chunk.min_chars', 200));

        $blocks = $this->toBlocks($text, $maxChars);

        if ($blocks === []) {
            return [];
        }

        $chunks = [];
        $current = '';

        foreach ($blocks as $block) {
            if ($current === '') {
                $current = $block;
                continue;
            }

            $separator = "\n\n";
            $candidate = $current . $separator . $block;

            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
                continue;
            }

            $chunks[] = $current;

            $tail = $this->tailOverlap($current, $overlap);
            $blockLength = mb_strlen($block);
            $separatorLength = mb_strlen($separator);

            $allowedTailLength = max(0, $maxChars - $blockLength - $separatorLength);

            if ($tail !== '' && mb_strlen($tail) > $allowedTailLength) {
                $tail = mb_substr($tail, mb_strlen($tail) - $allowedTailLength);
            }

            $current = $tail === ''
                ? $block
                : $tail . $separator . $block;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        $filtered = [];

        foreach ($chunks as $index => $chunk) {
            $isLast = $index === count($chunks) - 1;

            if (mb_strlen($chunk) >= $minChars || $isLast) {
                $filtered[] = $chunk;
            }
        }

        return $filtered;
    }

    protected function toBlocks(string $text, int $maxChars): array
    {
        $paragraphs = preg_split('/\n{2,}/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $blocks = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) <= $maxChars) {
                $blocks[] = $paragraph;
                continue;
            }

            foreach ($this->splitLongText($paragraph, $maxChars) as $piece) {
                $blocks[] = $piece;
            }
        }

        return $blocks;
    }

    protected function splitLongText(string $text, int $maxChars): array
    {
        $sentences = preg_split('/(?<=[.!?。！？])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];

        $pieces = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if ($sentence === '') {
                continue;
            }

            if (mb_strlen($sentence) > $maxChars) {
                if ($current !== '') {
                    $pieces[] = $current;
                    $current = '';
                }

                foreach ($this->splitByWords($sentence, $maxChars) as $wordChunk) {
                    $pieces[] = $wordChunk;
                }

                continue;
            }

            $candidate = $current === ''
                ? $sentence
                : $current . ' ' . $sentence;

            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $pieces[] = $current;
            }

            $current = $sentence;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }

    protected function splitByWords(string $text, int $maxChars): array
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $chunks = [];
        $current = '';

        foreach ($words as $word) {
            if (mb_strlen($word) > $maxChars) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }

                foreach (mb_str_split($word, $maxChars) as $hardChunk) {
                    $chunks[] = $hardChunk;
                }

                continue;
            }

            $candidate = $current === ''
                ? $word
                : $current . ' ' . $word;

            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
            }

            $current = $word;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    protected function tailOverlap(string $text, int $overlap): string
    {
        if ($overlap <= 0) {
            return '';
        }

        $length = mb_strlen($text);

        if ($length <= $overlap) {
            return $text;
        }

        $tail = mb_substr($text, $length - $overlap);

        $firstSpace = mb_strpos($tail, ' ');

        if ($firstSpace !== false && $firstSpace < 30) {
            $tail = mb_substr($tail, $firstSpace + 1);
        }

        return trim($tail);
    }
}
