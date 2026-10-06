<?php

namespace App\Retrieval;

use App\Knowledge\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only class allowed to run raw SELECTs against document_chunks.
 * MariaDB uses the vector index only for "ORDER BY VEC_DISTANCE_COSINE(...) LIMIT n" with no other
 * predicates, so candidates come from an indexed subquery and audience/status filters apply outside.
 */
final class ChunkSearchRepository
{
    /**
     * @param  list<float>  $queryVector
     * @return list<SearchHit> ordered by distance asc
     */
    public function search(array $queryVector, User $user, int $topK, int $candidateLimit): array
    {
        $candidates = DB::table('document_chunks')
            ->selectRaw('id, VEC_DISTANCE_COSINE(embedding, VEC_FromText(?)) AS distance', [json_encode($queryVector)])
            ->orderBy('distance')
            ->limit(max($candidateLimit, $topK));

        $query = DB::table('document_chunks as c')
            ->joinSub($candidates, 't', 't.id', '=', 'c.id')
            ->join('documents as d', 'd.id', '=', 'c.document_id')
            ->whereNull('d.deleted_at')
            ->where('d.status', 'ready')
            ->select(['c.id', 'c.document_id', 'd.title', 'c.page', 'c.heading', 'c.content', 't.distance'])
            ->orderBy('t.distance')
            ->limit($topK);

        Document::visibilityWhere($query, $user, 'd');

        return array_map(fn (object $row) => new SearchHit(
            (int) $row->id, (int) $row->document_id, (string) $row->title,
            $row->page === null ? null : (int) $row->page, $row->heading, (string) $row->content, (float) $row->distance,
        ), $query->get()->all());
    }

    public function findVisible(int $chunkId, User $user): ?SearchHit
    {
        $query = DB::table('document_chunks as c')
            ->join('documents as d', 'd.id', '=', 'c.document_id')
            ->whereNull('d.deleted_at')
            ->where('c.id', $chunkId)
            ->select(['c.id', 'c.document_id', 'd.title', 'c.page', 'c.heading', 'c.content']);

        Document::visibilityWhere($query, $user, 'd');

        $row = $query->first();

        return $row === null ? null : new SearchHit(
            (int) $row->id, (int) $row->document_id, (string) $row->title,
            $row->page === null ? null : (int) $row->page, $row->heading, (string) $row->content, 0.0,
        );
    }
}
