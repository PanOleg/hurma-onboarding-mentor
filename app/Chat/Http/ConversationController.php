<?php

namespace App\Chat\Http;

use App\Chat\Models\Conversation;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Conversation::query()->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')->orderByDesc('id');

        return ConversationResource::collection($query->paginate(max(1, min((int) $request->query('per_page', 25), 100))));
    }

    public function store(Request $request): JsonResponse
    {
        $conversation = Conversation::query()->create(['user_id' => $request->user()->id]);

        return ConversationResource::make($conversation)->response()->setStatusCode(201);
    }

    public function messages(Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        return MessageResource::collection(
            $conversation->messages()->with('citations.chunk.document')->orderBy('id')->get(),
        );
    }
}
