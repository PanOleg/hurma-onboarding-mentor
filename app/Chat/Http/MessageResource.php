<?php

namespace App\Chat\Http;

use App\Chat\Models\Message;
use App\Chat\Models\MessageCitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
final class MessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'status' => $this->status,
            'grounded' => $this->grounded,
            'rewritten_question' => $this->rewritten_question,
            'latency_ms' => $this->latency_ms,
            'created_at' => $this->created_at,
            'citations' => $this->citations->map(fn (MessageCitation $c): array => [
                'marker' => $c->marker,
                'chunk_id' => $c->chunk_id,
                'quote' => $c->quote,
                'document_id' => $c->chunk?->document_id,
                'document_title' => $c->chunk?->document?->title,
                'page' => $c->chunk?->page,
                'deleted' => $c->chunk === null || $c->chunk->document === null,
            ])->all(),
        ];
    }
}
