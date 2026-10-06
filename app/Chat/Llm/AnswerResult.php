<?php

namespace App\Chat\Llm;

final readonly class AnswerResult
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
        public string $stopReason,
        public string $model,
    ) {}
}
