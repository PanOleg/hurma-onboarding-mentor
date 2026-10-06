<?php

namespace App\Chat\Llm;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Messages\Message;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\RawMessageStartEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextDelta;
use App\Chat\Contracts\LlmClient;

final class AnthropicLlmClient implements LlmClient
{
    public function __construct(
        private readonly Client $client,
        private readonly string $answerModel,
        private readonly string $helperModel,
    ) {}

    public function streamAnswer(string $system, string $user, callable $onDelta): AnswerResult
    {
        $text = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $stopReason = 'end_turn';
        try {
            $stream = $this->client->messages->createStream(
                model: $this->answerModel,
                maxTokens: 2048,
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                messages: [['role' => 'user', 'content' => $user]],
                outputConfig: ['effort' => 'low'],
            );
            foreach ($stream as $event) {
                if ($event instanceof RawMessageStartEvent) {
                    $inputTokens = $event->message->usage->inputTokens ?? 0;
                } elseif ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                    $text .= $event->delta->text;
                    $onDelta($event->delta->text);
                } elseif ($event instanceof RawMessageDeltaEvent) {
                    $outputTokens = $event->usage->outputTokens;
                    $stopReason = $event->delta->stopReason ?? $stopReason;
                }
            }
        } catch (APIConnectionException|APIStatusException $e) {
            throw new LlmException(LlmException::UNAVAILABLE, $e->getMessage());
        }

        if ($stopReason === 'refusal') {
            throw new LlmException(LlmException::REFUSAL, 'model refused');
        }
        if ($stopReason === 'max_tokens') {
            throw new LlmException(LlmException::TRUNCATED, 'hit max_tokens');
        }

        return new AnswerResult($text, (int) $inputTokens, (int) $outputTokens, $stopReason, $this->answerModel);
    }

    public function rewriteQuestion(string $system, string $user): string
    {
        $message = $this->create($this->helperModel, 256, $system, $user);
        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                return trim($block->text);
            }
        }
        throw new LlmException(LlmException::UNAVAILABLE, 'no text block in rewrite response');
    }

    public function checkGrounding(string $system, string $user): GroundingResult
    {
        $message = $this->create($this->helperModel, 256, $system, $user, ['format' => GroundingResult::class]);
        $parsed = $message->parsedOutput();
        if (! $parsed instanceof GroundingResult) {
            throw new LlmException(LlmException::UNAVAILABLE, 'unparsable grounding output');
        }

        return $parsed;
    }

    /** @param array<string, mixed>|null $outputConfig */
    private function create(string $model, int $maxTokens, string $system, string $user, ?array $outputConfig = null): Message
    {
        try {
            $args = ['model' => $model, 'maxTokens' => $maxTokens, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $user]]];
            if ($outputConfig !== null) {
                $args['outputConfig'] = $outputConfig;
            }
            $message = $this->client->messages->create(...$args);
        } catch (APIConnectionException|APIStatusException $e) {
            throw new LlmException(LlmException::UNAVAILABLE, $e->getMessage());
        }
        if ($message->stopReason === 'refusal') {
            throw new LlmException(LlmException::REFUSAL, 'model refused');
        }

        return $message;
    }
}
