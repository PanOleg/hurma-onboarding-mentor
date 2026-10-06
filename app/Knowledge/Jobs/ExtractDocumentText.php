<?php

namespace App\Knowledge\Jobs;

use App\Knowledge\Contracts\TextExtractor;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\IngestionStep;
use App\Knowledge\Extraction\ExtractedPage;
use App\Knowledge\Ingestion\IngestionArtifacts;
use Illuminate\Support\Facades\Storage;

final class ExtractDocumentText extends IngestionJob
{
    protected function step(): IngestionStep
    {
        return IngestionStep::Extract;
    }

    protected function statusWhileRunning(): DocumentStatus
    {
        return DocumentStatus::Extracting;
    }

    public function handle(TextExtractor $extractor): void
    {
        $document = $this->begin();
        if ($document === null) {
            return;
        }
        $disk = Storage::disk('local');
        $pages = $extractor->extract($disk->path($document->storage_path), $document->mime);
        $nonEmpty = array_filter($pages, fn (ExtractedPage $p) => trim($p->text) !== '');
        if ($nonEmpty === []) {
            $e = new NoTextLayerException('document has no extractable text');
            $this->fail($e);
            throw $e;
        }
        $disk->put(IngestionArtifacts::pagesPath($document), json_encode(
            array_map(fn (ExtractedPage $p) => ['page' => $p->page, 'text' => $p->text], $pages),
            JSON_UNESCAPED_UNICODE
        ));
        $this->finish($document, DocumentStatus::Extracting);
    }
}
