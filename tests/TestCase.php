<?php

namespace Tests;

use App\Knowledge\Contracts\EmbeddingProvider;
use App\Knowledge\Contracts\TextExtractor;
use App\Knowledge\Embedding\FakeEmbeddingProvider;
use App\Knowledge\Extraction\FakeTextExtractor;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bindFakes();
    }

    protected function bindFakes(): void
    {
        $this->app->singleton(FakeEmbeddingProvider::class);
        $this->app->bind(EmbeddingProvider::class, FakeEmbeddingProvider::class);
        $this->app->singleton(FakeTextExtractor::class);
        $this->app->bind(TextExtractor::class, FakeTextExtractor::class);
        // Task 11 adds the LlmClient fake here.
    }
}
