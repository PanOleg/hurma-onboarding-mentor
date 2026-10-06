<?php

use App\Knowledge\Embedding\EmbeddingException;
use App\Knowledge\Embedding\HttpEmbeddingProvider;
use Illuminate\Support\Facades\Http;

it('posts passages with kind=passage and returns vectors', function () {
    Http::fake(['embedder.invalid/embed' => Http::response([
        'vectors' => [array_fill(0, 384, 0.1), array_fill(0, 384, 0.2)], 'model' => 'm', 'dim' => 384,
    ])]);

    $vectors = app(HttpEmbeddingProvider::class)->embedPassages(['a', 'b']);

    expect($vectors)->toHaveCount(2);
    Http::assertSent(fn ($req) => $req['kind'] === 'passage' && $req['texts'] === ['a', 'b']);
});

it('uses kind=query for a single query', function () {
    Http::fake(['embedder.invalid/embed' => Http::response(['vectors' => [array_fill(0, 384, 0.1)], 'model' => 'm', 'dim' => 384])]);

    app(HttpEmbeddingProvider::class)->embedQuery('q');

    Http::assertSent(fn ($req) => $req['kind'] === 'query' && $req['texts'] === ['q']);
});

it('throws BAD_DIMENSION when the sidecar returns a wrong vector size', function () {
    Http::fake(['embedder.invalid/embed' => Http::response(['vectors' => [array_fill(0, 768, 0.1)], 'model' => 'm', 'dim' => 768])]);

    expect(fn () => app(HttpEmbeddingProvider::class)->embedQuery('q'))
        ->toThrow(EmbeddingException::class, EmbeddingException::BAD_DIMENSION);
});

it('throws UNAVAILABLE on connection error or 5xx', function () {
    Http::fake(['embedder.invalid/embed' => Http::response('boom', 503)]);

    expect(fn () => app(HttpEmbeddingProvider::class)->embedQuery('q'))
        ->toThrow(EmbeddingException::class, EmbeddingException::UNAVAILABLE);
});
