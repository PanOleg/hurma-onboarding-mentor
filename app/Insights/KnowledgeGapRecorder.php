<?php

namespace App\Insights;

use App\Insights\Models\KnowledgeGap;
use Illuminate\Support\Facades\DB;

final class KnowledgeGapRecorder
{
    public function __construct(private readonly QuestionNormalizer $normalizer) {}

    public function recordNoAnswer(string $question): KnowledgeGap
    {
        return $this->upsert($question, 'open');
    }

    public function recordNeedsReview(string $question): KnowledgeGap
    {
        return $this->upsert($question, 'needs_review');
    }

    private function upsert(string $question, string $status): KnowledgeGap
    {
        $normalized = $this->normalizer->normalize($question);

        return DB::transaction(function () use ($question, $normalized, $status) {
            $gap = KnowledgeGap::query()->where('question_normalized', $normalized)->lockForUpdate()->first();
            if ($gap === null) {
                return KnowledgeGap::query()->create([
                    'question_normalized' => $normalized, 'question_example' => mb_substr($question, 0, 2000),
                    'occurrences' => 1, 'status' => $status, 'last_asked_at' => now(),
                ]);
            }
            $gap->occurrences++;
            $gap->last_asked_at = now();
            if ($gap->status === 'open' && $status === 'needs_review') {
                $gap->status = 'needs_review';
            }
            $gap->save();

            return $gap;
        });
    }
}
