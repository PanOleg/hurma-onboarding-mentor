<?php

namespace App\Knowledge\Http;

use App\Http\Controllers\Controller;
use App\Retrieval\ChunkSearchRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChunkController extends Controller
{
    public function show(Request $request, int $id, ChunkSearchRepository $chunks): JsonResponse
    {
        $hit = $chunks->findVisible($id, $request->user());
        abort_if($hit === null, 404);

        return response()->json(['data' => [
            'id' => $hit->chunkId,
            'document_id' => $hit->documentId,
            'document_title' => $hit->documentTitle,
            'page' => $hit->page,
            'heading' => $hit->heading,
            'content' => $hit->content,
        ]]);
    }
}
