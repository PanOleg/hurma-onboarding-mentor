<?php

use App\Knowledge\Chunking\Chunker;
use App\Knowledge\Extraction\ExtractedPage;

function words(int $n, string $w = 'слово'): string
{
    return implode(' ', array_fill(0, $n, $w));
}

it('returns no chunks for empty pages', function () {
    expect((new Chunker)->chunk([new ExtractedPage(1, "  \n ")]))->toBe([]);
});

it('keeps a short document as one chunk with page and heading', function () {
    $chunks = (new Chunker)->chunk([new ExtractedPage(1, "# Відпустки\n\nКожен має 24 дні.")]);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->position)->toBe(0)
        ->and($chunks[0]->page)->toBe(1)
        ->and($chunks[0]->heading)->toBe('Відпустки')
        ->and($chunks[0]->content)->toContain('24 дні')
        ->and($chunks[0]->tokenCount)->toBe(Chunker::estimateTokens($chunks[0]->content));
});

it('splits long text into chunks near the target size with overlap', function () {
    $paragraphs = [];
    for ($i = 0; $i < 12; $i++) {
        $paragraphs[] = "Абзац $i. ".implode(' ', array_map(fn ($j) => "w{$i}x{$j}", range(1, 60)));
    }
    $chunks = (new Chunker(400, 60, 600))->chunk([new ExtractedPage(1, implode("\n\n", $paragraphs))]);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $c) {
        expect($c->tokenCount)->toBeLessThanOrEqual(600);
    }
    // overlap: chunk 1 starts with the last 240 characters of chunk 0 (chunk content is trimmed, so a leading space of the tail is dropped)
    expect(str_starts_with($chunks[1]->content, ltrim(mb_substr($chunks[0]->content, -240))))->toBeTrue();
});

it('starts a new chunk at each markdown heading and tracks the heading', function () {
    $text = "# Розділ A\n\n".words(50)."\n\n## Розділ B\n\n".words(50);
    $chunks = (new Chunker)->chunk([new ExtractedPage(1, $text)]);

    expect(array_map(fn ($c) => $c->heading, $chunks))->toBe(['Розділ A', 'Розділ B']);
});

it('splits an oversized paragraph by sentences', function () {
    $sentence = words(30).'. ';
    $huge = str_repeat($sentence, 40); // ~1200 words, far above max
    $chunks = (new Chunker(400, 60, 600))->chunk([new ExtractedPage(1, $huge)]);

    expect(count($chunks))->toBeGreaterThan(2);
    foreach ($chunks as $c) {
        expect($c->tokenCount)->toBeLessThanOrEqual(600);
    }
});

it('records the page where each chunk starts', function () {
    $chunks = (new Chunker)->chunk([new ExtractedPage(1, 'Сторінка один.'), new ExtractedPage(2, 'Сторінка два.')]);
    // both tiny paragraphs merge into one chunk that starts on page 1
    expect($chunks)->toHaveCount(1)->and($chunks[0]->page)->toBe(1);

    $chunks = (new Chunker)->chunk([new ExtractedPage(1, words(380)), new ExtractedPage(2, words(380))]);
    expect($chunks[0]->page)->toBe(1)->and(end($chunks)->page)->toBe(2);
});

it('treats CRLF and BOM input like LF input', function () {
    $lf = "# Заголовок\n\nАбзац один.\n\nАбзац два.";
    $crlf = "\xEF\xBB\xBF# Заголовок\r\n\r\nАбзац один.\r\n\r\nАбзац два.";
    $a = (new Chunker)->chunk([new ExtractedPage(1, $lf)]);
    $b = (new Chunker)->chunk([new ExtractedPage(1, $crlf)]);

    expect(array_map(fn ($c) => [$c->heading, $c->content], $b))->toBe(array_map(fn ($c) => [$c->heading, $c->content], $a));
});

it('detects numbered and uppercase headings in plain text from pdf', function () {
    $text = "1. ЗАГАЛЬНІ ПОЛОЖЕННЯ\n".words(30)."\n\nПОРЯДОК НАДАННЯ\n".words(30);
    $chunks = (new Chunker)->chunk([new ExtractedPage(1, $text)]);

    expect($chunks[0]->heading)->toBe('1. ЗАГАЛЬНІ ПОЛОЖЕННЯ');
});

it('hard-splits delimiter-less text and keeps every word', function () {
    $input = implode(' ', array_map(fn ($j) => "w$j", range(1, 600))).' '.words(600);
    $chunks = (new Chunker(400, 60, 600))->chunk([new ExtractedPage(1, $input)]);

    expect(count($chunks))->toBeGreaterThan(2);
    $seen = [];
    foreach ($chunks as $c) {
        expect($c->tokenCount)->toBeLessThanOrEqual(600);
        foreach (preg_split('/\s+/u', $c->content) as $w) {
            $seen[$w] = true;
        }
    }
    foreach (range(1, 600) as $j) {
        expect($seen)->toHaveKey("w$j");
    }

    $blob = str_repeat('x', 5000);
    $chunks = (new Chunker(400, 60, 600))->chunk([new ExtractedPage(1, $blob)]);
    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $c) {
        expect($c->tokenCount)->toBeLessThanOrEqual(600);
    }
});

it('keeps heading text in chunk content and does not treat plain numbers as headings', function () {
    $chunks = (new Chunker)->chunk([new ExtractedPage(1, "# Відпустки\n\nКожен має 24 дні.\n\n24 календарні дні щороку.")]);

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]->heading)->toBe('Відпустки')
        ->and($chunks[0]->content)->toStartWith("Відпустки\n\n")
        ->and($chunks[0]->content)->toContain('24 календарні дні');
});
