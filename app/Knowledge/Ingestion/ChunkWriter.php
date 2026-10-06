<?php

namespace App\Knowledge\Ingestion;

use App\Knowledge\Chunking\Chunk;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\DB;

final class ChunkWriter
{
    /**
     * @param  list<Chunk>  $chunks
     * @param  list<list<float>>  $vectors
     */
    public function insertBatch(Document $document, array $chunks, array $vectors): void
    {
        if (count($chunks) !== count($vectors)) {
            throw new \InvalidArgumentException('chunks and vectors length mismatch');
        }
        DB::transaction(function () use ($document, $chunks, $vectors) {
            $now = now();
            foreach ($chunks as $i => $chunk) {
                DB::insert(
                    'INSERT INTO document_chunks (document_id, position, page, heading, content, token_count, embedding, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, VEC_FromText(?), ?, ?)',
                    [$document->id, $chunk->position, $chunk->page, $chunk->heading, $chunk->content, $chunk->tokenCount,
                        json_encode($vectors[$i]), $now, $now]
                );
            }
        });
    }
}
