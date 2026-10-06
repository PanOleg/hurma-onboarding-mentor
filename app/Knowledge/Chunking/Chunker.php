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
        $pendingHeading = false;

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
                $pendingHeading = true;

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
                    if ($pendingHeading && $heading !== null) {
                        // keep the heading text in the content so it is embedded too
                        $candidate = $heading."\n\n".$candidate;
                    }
                    $pendingHeading = false;
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
        if (preg_match('/^\d+(\.\d+)*\.\s+\S/u', $line) && ! str_ends_with($line, '.')) {
            return true;
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $line) ?? '';

        return mb_strlen($letters) >= 4 && $letters === mb_strtoupper($letters);
    }

    private function cleanHeading(string $line): string
    {
        return trim(preg_replace('/^#{1,6}\s+/u', '', trim($line)) ?? $line);
    }

    /** Max size of one piece; leaves room for a heading line prepended to the chunk. */
    private function pieceLimit(): int
    {
        return max(1, $this->maxTokens - 21);
    }

    /** @return list<string> */
    private function splitOversized(string $paragraph): array
    {
        if (self::estimateTokens($paragraph) <= $this->pieceLimit()) {
            return [$paragraph];
        }
        $sentences = [];
        foreach (preg_split('/(?<=[.!?…])\s+/u', $paragraph) ?: [$paragraph] as $s) {
            array_push($sentences, ...(self::estimateTokens($s) > $this->pieceLimit() ? $this->splitLong($s, 0) : [$s]));
        }
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

    /**
     * Split delimiter-less oversized text: by newline, then ';', then whitespace, finally hard by characters.
     *
     * @return list<string>
     */
    private function splitLong(string $text, int $level): array
    {
        if (self::estimateTokens($text) <= $this->targetTokens) {
            return [$text];
        }
        if ($level >= 3) {
            return mb_str_split($text, $this->pieceLimit() * 4);
        }
        [$pattern, $glue] = [["/\n/u", "\n"], ['/;/u', ';'], ['/\s+/u', ' ']][$level];
        $pieces = [];
        $current = '';
        foreach (preg_split($pattern, $text) ?: [$text] as $unit) {
            if ($unit === '') {
                continue;
            }
            foreach (self::estimateTokens($unit) > $this->targetTokens ? $this->splitLong($unit, $level + 1) : [$unit] as $u) {
                $candidate = $current === '' ? $u : $current.$glue.$u;
                if ($current !== '' && self::estimateTokens($candidate) > $this->targetTokens) {
                    $pieces[] = $current;
                    $candidate = $u;
                }
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }
}
