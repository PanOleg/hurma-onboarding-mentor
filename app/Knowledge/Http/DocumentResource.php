<?php

namespace App\Knowledge\Http;

use App\Knowledge\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Document */
final class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'original_filename' => $this->original_filename,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'audience_type' => $this->audience_type,
            'audience_value' => $this->audience_value,
            'status' => $this->status,
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'version' => $this->version,
            'chunks_count' => $this->chunks_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'ingestion_runs' => $this->whenLoaded('ingestionRuns', fn () => $this->ingestionRuns->map(fn ($r) => [
                'step' => $r->step->value,
                'status' => $r->status,
                'attempt' => $r->attempt,
                'started_at' => $r->started_at,
                'finished_at' => $r->finished_at,
                'error' => $r->error,
            ])),
        ];
    }
}
