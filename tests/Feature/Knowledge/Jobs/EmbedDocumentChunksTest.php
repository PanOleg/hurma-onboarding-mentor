<?php

use App\Knowledge\Contracts\EmbeddingProvider;
use App\Knowledge\Embedding\EmbeddingException;
use App\Knowledge\Embedding\FakeEmbeddingProvider;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\FailureCode;
use App\Knowledge\Ingestion\ChunkWriter;
use App\Knowledge\Ingestion\IngestionArtifacts;
use App\Knowledge\Jobs\EmbedDocumentChunks;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function putChunks(Document $doc, int $n): void
{
    $chunks = [];
    for ($i = 0; $i < $n; $i++) {
        $chunks[] = ['position' => $i, 'page' => 1, 'heading' => null, 'content' => "chunk $i", 'tokenCount' => 2];
    }
    Storage::disk('local')->put(IngestionArtifacts::chunksPath($doc), json_encode($chunks));
}

it('embeds in batches of 32 and sets chunks_count', function () {
    config(['rag.embedder.batch_size' => 32]);
    $doc = Document::factory()->status(DocumentStatus::Chunking)->create();
    putChunks($doc, 70);
    $spy = new class implements EmbeddingProvider
    {
        /** @var list<int> */
        public array $sizes = [];

        public function embedPassages(array $texts): array
        {
            $this->sizes[] = count($texts);

            return (new FakeEmbeddingProvider)->embedPassages($texts);
        }

        public function embedQuery(string $text): array
        {
            return (new FakeEmbeddingProvider)->embedQuery($text);
        }
    };
    app()->instance(EmbeddingProvider::class, $spy);

    (new EmbedDocumentChunks($doc->id))->handle(app(EmbeddingProvider::class), app(ChunkWriter::class));

    expect($spy->sizes)->toBe([32, 32, 6])
        ->and($doc->refresh()->status)->toBe(DocumentStatus::Ready)
        ->and($doc->chunks_count)->toBe(70)
        ->and(DB::table('document_chunks')->where('document_id', $doc->id)->count())->toBe(70);
});

it('marks embedder_unavailable on provider failure', function () {
    $doc = Document::factory()->status(DocumentStatus::Chunking)->create();
    putChunks($doc, 3);
    $this->app->bind(EmbeddingProvider::class, fn () => new class implements EmbeddingProvider
    {
        public function embedPassages(array $texts): array
        {
            throw new EmbeddingException(EmbeddingException::UNAVAILABLE, 'down');
        }

        public function embedQuery(string $text): array
        {
            throw new EmbeddingException(EmbeddingException::UNAVAILABLE, 'down');
        }
    });

    $job = new EmbedDocumentChunks($doc->id);
    expect(fn () => $job->handle(app(EmbeddingProvider::class), app(ChunkWriter::class)))->toThrow(EmbeddingException::class);
    $job->failed(new EmbeddingException(EmbeddingException::UNAVAILABLE, 'down'));

    expect($doc->refresh()->failure_code)->toBe(FailureCode::EmbedderUnavailable);
});
