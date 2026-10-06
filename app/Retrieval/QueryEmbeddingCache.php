<?php

namespace App\Retrieval;

use App\Knowledge\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Cache;

final class QueryEmbeddingCache
{
    public function __construct(private readonly EmbeddingProvider $provider) {}

    /** @return list<float> */
    public function embedQuery(string $question): array
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($question))) ?? $question;
        $normalized = trim(preg_replace('/\s+([?!.,])/u', '$1', $normalized) ?? $normalized);

        return Cache::remember('embed:q:'.sha1($normalized), 3600, fn () => $this->provider->embedQuery($question));
    }
}
