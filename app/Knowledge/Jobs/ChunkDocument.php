<?php

namespace App\Knowledge\Jobs;

use App\Knowledge\Chunking\Chunk;
use App\Knowledge\Chunking\Chunker;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\IngestionStep;
use App\Knowledge\Extraction\ExtractedPage;
use App\Knowledge\Ingestion\IngestionArtifacts;
use Illuminate\Support\Facades\Storage;

final class ChunkDocument extends IngestionJob
{
    protected function step(): IngestionStep
    {
        return IngestionStep::Chunk;
    }

    protected function statusWhileRunning(): DocumentStatus
    {
        return DocumentStatus::Chunking;
    }

    public function handle(Chunker $chunker): void
    {
        $document = $this->begin();
        if ($document === null) {
            return;
        }
        $this->guarded(function () use ($document, $chunker) {
            $disk = Storage::disk('local');
            $pages = array_map(
                fn (array $p) => new ExtractedPage((int) $p['page'], (string) $p['text']),
                json_decode((string) $disk->get(IngestionArtifacts::pagesPath($document)), true, flags: JSON_THROW_ON_ERROR)
            );
            $chunks = $chunker->chunk($pages);
            $disk->put(IngestionArtifacts::chunksPath($document), json_encode(array_map(fn (Chunk $c) => [
                'position' => $c->position, 'page' => $c->page, 'heading' => $c->heading, 'content' => $c->content, 'tokenCount' => $c->tokenCount,
            ], $chunks), JSON_UNESCAPED_UNICODE));
            $this->finish($document, DocumentStatus::Chunking);
        });
    }
}
