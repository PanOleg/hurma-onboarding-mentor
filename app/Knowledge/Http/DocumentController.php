<?php

namespace App\Knowledge\Http;

use App\Http\Controllers\Controller;
use App\Knowledge\Enums\AudienceType;
use App\Knowledge\Ingestion\DocumentUploader;
use App\Knowledge\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class DocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Document::class);
        $query = Document::query()->latest('id');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return DocumentResource::collection($query->paginate(max(1, min((int) $request->query('per_page', 25), 100))));
    }

    public function store(StoreDocumentRequest $request, DocumentUploader $uploader): JsonResponse
    {
        $result = $uploader->upload(
            $request->file('file'), $request->string('title')->toString(), AudienceType::from($request->input('audience_type')),
            $request->input('audience_value'), $request->user(),
        );

        return DocumentResource::make($result['document'])->response()->setStatusCode($result['created'] ? 202 : 200);
    }

    public function show(Document $document): DocumentResource
    {
        Gate::authorize('view', $document);

        return DocumentResource::make($document->load('ingestionRuns'));
    }
}
