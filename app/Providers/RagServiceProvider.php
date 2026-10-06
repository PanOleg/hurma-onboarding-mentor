<?php

namespace App\Providers;

use App\Knowledge\Chunking\Chunker;
use App\Knowledge\Contracts\EmbeddingProvider;
use App\Knowledge\Contracts\TextExtractor;
use App\Knowledge\Embedding\HttpEmbeddingProvider;
use App\Knowledge\Extraction\CompositeTextExtractor;
use App\Knowledge\Extraction\HttpTextExtractor;
use App\Knowledge\Extraction\LocalTextExtractor;
use Illuminate\Support\ServiceProvider;

class RagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HttpEmbeddingProvider::class, fn () => new HttpEmbeddingProvider(
            config('rag.embedder.url'), config('rag.embedder.timeout_embed'), config('rag.embedding_dim'),
        ));
        $this->app->bind(EmbeddingProvider::class, HttpEmbeddingProvider::class);

        $this->app->singleton(HttpTextExtractor::class, fn () => new HttpTextExtractor(
            config('rag.embedder.url'), config('rag.embedder.timeout_extract'),
        ));
        $this->app->bind(TextExtractor::class, fn ($app) => new CompositeTextExtractor(
            new LocalTextExtractor, $app->make(HttpTextExtractor::class),
        ));

        $this->app->bind(Chunker::class, fn () => new Chunker(
            config('rag.chunk.target_tokens'), config('rag.chunk.overlap_tokens'), config('rag.chunk.max_tokens'),
        ));
    }
}
