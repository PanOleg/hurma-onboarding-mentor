<?php

use App\Knowledge\Contracts\EmbeddingProvider;
use App\Retrieval\QueryEmbeddingCache;

it('embeds once and serves repeated and differently-spaced questions from cache', function () {
    $calls = 0;
    $this->app->bind(EmbeddingProvider::class, function () use (&$calls) {
        return new class($calls) implements EmbeddingProvider
        {
            public function __construct(private int &$calls) {}

            public function embedPassages(array $texts): array
            {
                return [];
            }

            public function embedQuery(string $text): array
            {
                $this->calls++;

                return array_fill(0, 384, 0.5);
            }
        };
    });
    $cache = app(QueryEmbeddingCache::class);

    $a = $cache->embedQuery('Скільки днів відпустки?');
    $b = $cache->embedQuery('  скільки  днів відпустки ? ');

    expect($a)->toBe($b)->and($calls)->toBe(1);
});
