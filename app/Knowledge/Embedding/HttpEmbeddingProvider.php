<?php

namespace App\Knowledge\Embedding;

use App\Knowledge\Contracts\EmbeddingProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class HttpEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly int $dim,
    ) {}

    public function embedPassages(array $texts): array
    {
        return $this->call($texts, 'passage');
    }

    public function embedQuery(string $text): array
    {
        return $this->call([$text], 'query')[0];
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    private function call(array $texts, string $kind): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/embed', ['texts' => $texts, 'kind' => $kind]);
        } catch (ConnectionException $e) {
            throw new EmbeddingException(EmbeddingException::UNAVAILABLE, $e->getMessage());
        }

        if ($response->failed()) {
            throw new EmbeddingException(EmbeddingException::UNAVAILABLE, 'HTTP '.$response->status());
        }

        $vectors = $response->json('vectors');
        if (! is_array($vectors) || count($vectors) !== count($texts)) {
            throw new EmbeddingException(EmbeddingException::UNAVAILABLE, 'malformed response');
        }
        foreach ($vectors as $v) {
            if (! is_array($v)) {
                throw new EmbeddingException(EmbeddingException::UNAVAILABLE, 'malformed response');
            }
            if (count($v) !== $this->dim) {
                throw new EmbeddingException(EmbeddingException::BAD_DIMENSION, 'expected '.$this->dim.', got '.count($v));
            }
        }

        return array_map(fn (array $v) => array_map('floatval', array_values($v)), array_values($vectors));
    }
}
