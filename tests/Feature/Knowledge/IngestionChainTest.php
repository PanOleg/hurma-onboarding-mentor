<?php

use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Extraction\ExtractedPage;
use App\Knowledge\Extraction\FakeTextExtractor;
use App\Knowledge\Ingestion\DocumentIngestionService;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('runs extract -> chunk -> embed and marks the document ready (AC-1)', function () {
    $doc = Document::factory()->status(DocumentStatus::Uploaded)->create([
        'storage_path' => 'knowledge/abc.md', 'mime' => 'text/markdown',
    ]);
    Storage::disk('local')->put('knowledge/abc.md', "# Відпустки\n\nКожен має 24 дні.\n\n# Лікарняні\n\nПотрібна довідка.");

    app(DocumentIngestionService::class)->dispatchChain($doc);

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Ready)
        ->and($doc->chunks_count)->toBe(2)
        ->and(DB::table('document_chunks')->where('document_id', $doc->id)->count())->toBe(2)
        ->and($doc->ingestionRuns->pluck('step')->map->value->all())->toBe(['extract', 'chunk', 'embed'])
        ->and($doc->ingestionRuns->pluck('status')->unique()->all())->toBe(['done']);
});

it('uses the extractor for pdf pages and keeps page numbers on chunks', function () {
    $doc = Document::factory()->status(DocumentStatus::Uploaded)->create([
        'storage_path' => 'knowledge/x.pdf', 'mime' => 'application/pdf',
    ]);
    Storage::disk('local')->put('knowledge/x.pdf', '%PDF');
    $fake = app(FakeTextExtractor::class);
    $fake->pagesByPath[Storage::disk('local')->path('knowledge/x.pdf')] = [
        new ExtractedPage(1, 'Сторінка один про відпустки.'),
        new ExtractedPage(2, 'Сторінка два про лікарняні.'),
    ];

    app(DocumentIngestionService::class)->dispatchChain($doc);

    $pages = DB::table('document_chunks')->where('document_id', $doc->id)->pluck('page')->all();
    expect($doc->refresh()->status)->toBe(DocumentStatus::Ready)->and($pages)->toContain(1);
});
