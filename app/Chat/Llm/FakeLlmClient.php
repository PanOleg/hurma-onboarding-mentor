<?php

namespace App\Chat\Llm;

use App\Chat\Contracts\LlmClient;

final class FakeLlmClient implements LlmClient
{
    public string $nextAnswer = 'Відповідь на основі документів [1].';

    public ?string $nextRewrite = null;

    public bool $nextGrounded = true;

    public ?LlmException $throws = null;

    /** When set, `throws` applies only to this method name. */
    public ?string $throwsOn = null;

    /** @var array<string, list<array<string, string>>> */
    public array $calls = [];

    public function streamAnswer(string $system, string $user, callable $onDelta): AnswerResult
    {
        $this->record('streamAnswer', $system, $user);
        $this->maybeThrow(__FUNCTION__);
        $parts = preg_split('/(?<=\s)/u', $this->nextAnswer) ?: [$this->nextAnswer];
        foreach ($parts as $part) {
            if ($part !== '') {
                $onDelta($part);
            }
        }

        return new AnswerResult($this->nextAnswer, 1000, 50, 'end_turn', 'fake-answer');
    }

    public function rewriteQuestion(string $system, string $user): string
    {
        $this->record('rewriteQuestion', $system, $user);
        $this->maybeThrow(__FUNCTION__);
        if ($this->nextRewrite !== null) {
            return $this->nextRewrite;
        }
        preg_match('/Останнє питання: (.*)$/su', $user, $m);

        return trim($m[1] ?? $user);
    }

    public function checkGrounding(string $system, string $user): GroundingResult
    {
        $this->record('checkGrounding', $system, $user);
        $this->maybeThrow(__FUNCTION__);
        $r = new GroundingResult;
        $r->grounded = $this->nextGrounded;
        $r->reason = 'fake';

        return $r;
    }

    private function record(string $method, string $system, string $user): void
    {
        $this->calls[$method][] = ['system' => $system, 'user' => $user];
    }

    private function maybeThrow(string $method): void
    {
        if ($this->throws !== null && ($this->throwsOn === null || $this->throwsOn === $method)) {
            throw $this->throws;
        }
    }
}
