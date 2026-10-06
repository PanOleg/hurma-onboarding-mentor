<?php

use App\Chat\CitationParser;
use App\Retrieval\SearchHit;

function hits(int $n): array
{
    $out = [];
    for ($i = 1; $i <= $n; $i++) {
        $out[] = new SearchHit($i * 10, 1, 'Док', null, null, "фрагмент $i", 0.1 * $i);
    }

    return $out;
}

it('maps markers to hits in order of first appearance without duplicates', function () {
    $r = (new CitationParser)->parse('Так [2]. Також [1][2], і ще [1].', hits(3));

    expect(array_map(fn ($c) => [$c->marker, $c->hit->chunkId], $r['citations']))->toBe([[2, 20], [1, 10]])
        ->and($r['invalidMarkers'])->toBe([]);
});

it('drops out-of-range and zero markers and reports them', function () {
    $r = (new CitationParser)->parse('Текст [9] і [0] і [1].', hits(2));

    expect(array_map(fn ($c) => $c->marker, $r['citations']))->toBe([1])
        ->and($r['invalidMarkers'])->toBe([9, 0]);
});

it('returns nothing for an answer without markers', function () {
    $r = (new CitationParser)->parse('У базі знань цього немає.', hits(2));
    expect($r['citations'])->toBe([])->and($r['invalidMarkers'])->toBe([]);
});
