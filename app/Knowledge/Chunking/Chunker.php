<?php

namespace App\Knowledge\Chunking;

use App\Knowledge\Extraction\ExtractedPage;

final class Chunker
{
    public function __construct(
        private readonly int $targetTokens = 400,
        private readonly int $overlapTokens = 60,
        private readonly int $maxTokens = 600,
    ) {}

    public static function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * @param  list<ExtractedPage>  $pages
     * @return list<Chunk>
     */
    public function chunk(array $pages): array
    {
        $blocks = $this->blocks($pages);
        $chunks = [];
        $buffer = '';
        $bufferPage = null;
        $heading = null;
        $bufferHeading = null;
        $prevTail = '';

        $flush = function () use (&$chunks, &$buffer, &$bufferPage, &$bufferHeading, &$prevTail) {
            $content = trim($buffer);
            if ($content !== '') {
                $chunks[] = new Chunk(count($chunks), $bufferPage, $bufferHeading, $content, self::estimateTokens($content));
                $prevTail = mb_substr($content, -($this->overlapTokens * 4));
            }
            $buffer = '';
            $bufferPage = null;
        };

        foreach ($blocks as [$type, $text, $page]) {
            if ($type === 'heading') {
                $flush();
                $heading = $text;
                $prevTail = '';

                continue;
            }
            foreach ($this->splitOversized($text) as $piece) {
                $candidate = $buffer === '' ? $piece : $buffer."\n\n".$piece;
                if ($buffer !== '' && self::estimateTokens($candidate) > $this->targetTokens) {
                    $flush();
                    $candidate = $piece;
                    if ($prevTail !== '' && self::estimateTokens($prevTail."\n\n".$piece) <= $this->maxTokens) {
                        $candidate = $prevTail."\n\n".$piece;
                    }
                }
                if ($buffer === '') {
                    $bufferPage = $page;
                    $bufferHeading = $heading;
                }
                $buffer = $candidate;
            }
        }
        $flush();

        return $chunks;
    }

    /**
     * @param  list<ExtractedPage>  $pages
     * @return list<array{0:string,1:string,2:int}>
     */
    private function blocks(array $pages): array
    {
        $blocks = [];
        foreach ($pages as $page) {
            $text = preg_replace("/^\xEF\xBB\xBF/", '', $page->text) ?? $page->text;
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            foreach (preg_split("/\n\s*\n/u", $text) ?: [] as $raw) {
                $block = trim($raw);
                if ($block === '') {
                    continue;
                }
                // a heading line followed by body text inside one block (common in PDF text)
                $lines = explode("\n", $block);
                if (count($lines) > 1 && $this->isHeading($lines[0])) {
                    $blocks[] = ['heading', $this->cleanHeading($lines[0]), $page->page];
                    $block = trim(implode("\n", array_slice($lines, 1)));
                    if ($block === '') {
                        continue;
                    }
                } elseif ($this->isHeading($block)) {
                    $blocks[] = ['heading', $this->cleanHeading($block), $page->page];

                    continue;
                }
                $blocks[] = ['paragraph', $block, $page->page];
            }
        }

        return $blocks;
    }

    private function isHeading(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || mb_strlen($line) > 80 || str_contains($line, "\n")) {
            return false;
        }
        if (preg_match('/^#{1,6}\s+\S/u', $line)) {
            return true;
        }
        if (preg_match('/^\d+(\.\d+)*\.?\s+\S/u', $line) && ! str_ends_with($line, '.')) {
            return true;
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $line) ?? '';

        return mb_strlen($letters) >= 4 && $letters === mb_strtoupper($letters);
    }

    private function cleanHeading(string $line): string
    {
        return trim(preg_replace('/^#{1,6}\s+/u', '', trim($line)) ?? $line);
    }

    /** @return list<string> */
    private function splitOversized(string $paragraph): array
    {
        if (self::estimateTokens($paragraph) <= $this->maxTokens) {
            return [$paragraph];
        }
        $sentences = preg_split('/(?<=[.!?…])\s+/u', $paragraph) ?: [$paragraph];
        $pieces = [];
        $current = '';
        foreach ($sentences as $s) {
            $candidate = $current === '' ? $s : $current.' '.$s;
            if ($current !== '' && self::estimateTokens($candidate) > $this->targetTokens) {
                $pieces[] = $current;
                $candidate = $s;
            }
            $current = $candidate;
        }
        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }
}
