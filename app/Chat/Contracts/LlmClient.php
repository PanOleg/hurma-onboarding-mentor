<?php

namespace App\Chat\Contracts;

use App\Chat\Llm\AnswerResult;
use App\Chat\Llm\GroundingResult;

interface LlmClient
{
    /** Streams the answer; $onDelta receives each text fragment. */
    public function streamAnswer(string $system, string $user, callable $onDelta): AnswerResult;

    public function rewriteQuestion(string $system, string $user): string;

    public function checkGrounding(string $system, string $user): GroundingResult;
}
