<?php

use App\Knowledge\Contracts\TextExtractor;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\FailureCode;
use App\Knowledge\Extraction\ExtractedPage;
use App\Knowledge\Extraction\ExtractionException;
use App\Knowledge\Extraction\FakeTextExtractor;
use App\Knowledge\Jobs\ExtractDocumentText;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('marks no_text_layer when extractor returns only empty pages', function () {
    $doc = Document::factory()->status(DocumentStatus::Uploaded)->create(['storage_path' => 'knowledge/scan.pdf', 'mime' => 'application/pdf']);
    Storage::disk('local')->put('knowledge/scan.pdf', '%PDF');
    app(FakeTextExtractor::class)->pagesByPath[Storage::disk('local')->path('knowledge/scan.pdf')] = [new ExtractedPage(1, '  '), new ExtractedPage(2, '')];

    $job = new ExtractDocumentText($doc->id);
    try {
        $job->handle(app(TextExtractor::class));
    } catch (Throwable $e) {
        $job->failed($e);
    }

    expect($doc->refresh()->status)->toBe(DocumentStatus::Failed)
        ->and($doc->failure_code)->toBe(FailureCode::NoTextLayer);
});

it('marks extractor_unavailable in failed() when the sidecar is down', function () {
    $doc = Document::factory()->status(DocumentStatus::Uploaded)->create(['storage_path' => 'knowledge/a.pdf', 'mime' => 'application/pdf']);
    Storage::disk('local')->put('knowledge/a.pdf', '%PDF');
    app(FakeTextExtractor::class)->throws = new ExtractionException(ExtractionException::UNAVAILABLE, 'down');

    $job = new ExtractDocumentText($doc->id);
    expect(fn () => $job->handle(app(TextExtractor::class)))->toThrow(ExtractionException::class);
    $job->failed(new ExtractionException(ExtractionException::UNAVAILABLE, 'down'));

    $doc->refresh();
    expect($doc->status)->toBe(DocumentStatus::Failed)
        ->and($doc->failure_code)->toBe(FailureCode::ExtractorUnavailable)
        ->and($doc->ingestionRuns->last()->status)->toBe('failed');
});

it('exits silently when the document was deleted', function () {
    $doc = Document::factory()->status(DocumentStatus::Uploaded)->create();
    $doc->delete();

    (new ExtractDocumentText($doc->id))->handle(app(TextExtractor::class));

    expect(Document::withTrashed()->find($doc->id)->status)->toBe(DocumentStatus::Uploaded);
});
