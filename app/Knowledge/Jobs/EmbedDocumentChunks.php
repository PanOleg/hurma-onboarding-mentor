<?php

namespace App\Knowledge\Jobs;

use App\Knowledge\Chunking\Chunk;
use App\Knowledge\Contracts\EmbeddingProvider;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Enums\IngestionStep;
use App\Knowledge\Ingestion\ChunkWriter;
use App\Knowledge\Ingestion\IngestionArtifacts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class EmbedDocumentChunks extends IngestionJob
{
    protected function step(): IngestionStep
    {
        return IngestionStep::Embed;
    }

    protected function statusWhileRunning(): DocumentStatus
    {
        return DocumentStatus::Embedding;
    }

    public function handle(EmbeddingProvider $embeddings, ChunkWriter $writer): void
    {
        $document = $this->begin();
        if ($document === null) {
            return;
        }
        $this->guarded(function () use ($document, $embeddings, $writer) {
            $raw = json_decode((string) Storage::disk('local')->get(IngestionArtifacts::chunksPath($document)), true, flags: JSON_THROW_ON_ERROR);
            $chunks = array_map(fn (array $c) => new Chunk($c['position'], $c['page'], $c['heading'], $c['content'], $c['tokenCount']), $raw);

            // idempotency for retries: drop partial rows from a previous attempt
            DB::table('document_chunks')->where('document_id', $document->id)->delete();

            foreach (array_chunk($chunks, (int) config('rag.embedder.batch_size')) as $batch) {
                $vectors = $embeddings->embedPassages(array_map(fn (Chunk $c) => $c->content, $batch));
                $writer->insertBatch($document, $batch, $vectors);
            }
            $this->finish($document, DocumentStatus::Ready, ['chunks_count' => count($chunks)]);
        });
    }
}
