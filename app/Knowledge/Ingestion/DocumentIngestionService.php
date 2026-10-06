<?php

namespace App\Knowledge\Ingestion;

use App\Knowledge\Jobs\ChunkDocument;
use App\Knowledge\Jobs\EmbedDocumentChunks;
use App\Knowledge\Jobs\ExtractDocumentText;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\Bus;

final class DocumentIngestionService
{
    public function dispatchChain(Document $document): void
    {
        Bus::chain([
            new ExtractDocumentText($document->id),
            new ChunkDocument($document->id),
            new EmbedDocumentChunks($document->id),
        ])->onQueue('ingestion')->dispatch();
    }
}
