<?php

namespace App\Providers;

use Anthropic\Client;
use App\Chat\Contracts\LlmClient;
use App\Chat\Llm\AnthropicLlmClient;
use App\Chat\Llm\OpenAiCompatibleLlmClient;
use App\Knowledge\Chunking\Chunker;
use App\Knowledge\Contracts\EmbeddingProvider;
use App\Knowledge\Contracts\TextExtractor;
use App\Knowledge\Embedding\HttpEmbeddingProvider;
use App\Knowledge\Extraction\CompositeTextExtractor;
use App\Knowledge\Extraction\HttpTextExtractor;
use App\Knowledge\Extraction\LocalTextExtractor;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

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

        $this->app->singleton(AnthropicLlmClient::class, fn () => new AnthropicLlmClient(
            new Client(apiKey: (string) config('rag.anthropic.api_key')),
            config('rag.models.answer'), config('rag.models.helper'),
        ));
        $this->app->singleton(OpenAiCompatibleLlmClient::class, fn () => new OpenAiCompatibleLlmClient(
            (string) config('rag.openai_compatible.base_url'),
            (string) config('rag.openai_compatible.api_key'),
            (string) config('rag.openai_compatible.model_answer'),
            (string) config('rag.openai_compatible.model_helper'),
            (int) config('rag.openai_compatible.timeout'),
            (string) config('rag.openai_compatible.reasoning_effort'),
            (int) config('rag.openai_compatible.helper_max_tokens'),
        ));
        $this->app->bind(LlmClient::class, fn ($app) => match (config('rag.llm.driver')) {
            'anthropic' => $app->make(AnthropicLlmClient::class),
            'openai_compatible' => $app->make(OpenAiCompatibleLlmClient::class),
            default => throw new InvalidArgumentException('Unknown RAG_LLM_DRIVER: '.(string) config('rag.llm.driver')),
        });
    }
}
