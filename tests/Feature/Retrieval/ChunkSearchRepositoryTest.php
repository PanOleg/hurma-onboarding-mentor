<?php

use App\Knowledge\Chunking\Chunk;
use App\Knowledge\Embedding\FakeEmbeddingProvider;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Ingestion\ChunkWriter;
use App\Knowledge\Models\Department;
use App\Knowledge\Models\Document;
use App\Models\User;
use App\Retrieval\ChunkSearchRepository;

function seedChunks(Document $doc, array $texts): void
{
    $fake = app(FakeEmbeddingProvider::class);
    $chunks = [];
    foreach ($texts as $i => $t) {
        $chunks[] = new Chunk($i, 1, null, $t, 10);
    }
    app(ChunkWriter::class)->insertBatch($doc, $chunks, $fake->embedPassages($texts));
}

it('returns nearest chunks ordered by distance with document title', function () {
    $doc = Document::factory()->create(['title' => 'Відпустки']);
    seedChunks($doc, ['скільки днів відпустки має працівник', 'як налаштувати принтер', 'оформлення відпустки через HR']);
    $user = User::factory()->create();
    $q = app(FakeEmbeddingProvider::class)->embedQuery('днів відпустки');

    $hits = app(ChunkSearchRepository::class)->search($q, $user, 2, 100);

    expect($hits)->toHaveCount(2)
        ->and($hits[0]->documentTitle)->toBe('Відпустки')
        ->and($hits[0]->distance)->toBeLessThanOrEqual($hits[1]->distance)
        ->and($hits[0]->content)->toContain('відпустки');
});

it('never returns chunks of documents outside the user audience (AC-5)', function () {
    $eng = Department::factory()->create();
    $mkt = Department::factory()->create();
    $user = User::factory()->inDepartment($eng, 'developer')->create();
    $engDoc = Document::factory()->forDepartment($eng)->create();
    $mktDoc = Document::factory()->forDepartment($mkt)->create();
    $qaDoc = Document::factory()->forRole('qa')->create();
    seedChunks($engDoc, ['секретний план інженерів']);
    seedChunks($mktDoc, ['секретний план маркетингу']);
    seedChunks($qaDoc, ['секретний план тестувальників']);
    $q = app(FakeEmbeddingProvider::class)->embedQuery('секретний план');

    $hits = app(ChunkSearchRepository::class)->search($q, $user, 8, 100);

    expect(array_map(fn ($h) => $h->documentId, $hits))->toBe([$engDoc->id]);
});

it('skips documents that are not ready or soft-deleted', function () {
    $user = User::factory()->create();
    $ready = Document::factory()->create();
    $pending = Document::factory()->status(DocumentStatus::Embedding)->create();
    $deleted = Document::factory()->create();
    seedChunks($ready, ['текст готовий']);
    seedChunks($pending, ['текст готовий']);
    seedChunks($deleted, ['текст готовий']);
    $deleted->delete();
    $q = app(FakeEmbeddingProvider::class)->embedQuery('текст готовий');

    $hits = app(ChunkSearchRepository::class)->search($q, $user, 8, 100);

    expect(array_map(fn ($h) => $h->documentId, $hits))->toBe([$ready->id]);
});

it('returns an empty list when there are no chunks', function () {
    $q = app(FakeEmbeddingProvider::class)->embedQuery('будь-що');
    expect(app(ChunkSearchRepository::class)->search($q, User::factory()->create(), 8, 100))->toBe([]);
});
