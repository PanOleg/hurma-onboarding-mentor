<?php

namespace App\Chat\Llm;

use App\Chat\Contracts\LlmClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;

final class OpenAiCompatibleLlmClient implements LlmClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $answerModel,
        private readonly string $helperModel,
        private readonly int $timeout,
        private readonly string $reasoningEffort = '',
        private readonly int $helperMaxTokens = 1024,
    ) {}

    public function streamAnswer(string $system, string $user, callable $onDelta): AnswerResult
    {
        $response = $this->send([
            'model' => $this->answerModel,
            'messages' => $this->messages($system, $user),
            'stream' => true,
            'stream_options' => ['include_usage' => true],
            'max_tokens' => 2048,
            'temperature' => 0.2,
        ], stream: true);

        $text = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $finishReason = null;
        $done = false;

        $handleLine = function (string $line) use (&$text, &$inputTokens, &$outputTokens, &$finishReason, &$done, $onDelta): void {
            $line = trim($line);
            if (! str_starts_with($line, 'data:')) {
                return;
            }
            $payload = trim(substr($line, 5));
            if ($payload === '[DONE]') {
                $done = true;

                return;
            }
            $chunk = $payload === '' ? null : json_decode($payload, true);
            if (! is_array($chunk)) {
                return;
            }
            if (isset($chunk['error'])) {
                $error = $chunk['error'];
                $message = is_array($error) ? ($error['message'] ?? json_encode($error)) : $error;
                throw new LlmException(LlmException::UNAVAILABLE, 'stream error: '.mb_substr((string) (is_scalar($message) ? $message : json_encode($message)), 0, 300));
            }
            $delta = $chunk['choices'][0]['delta']['content'] ?? null;
            if (is_string($delta) && $delta !== '') {
                $text .= $delta;
                $onDelta($delta);
            }
            $finish = $chunk['choices'][0]['finish_reason'] ?? null;
            if (is_string($finish) && $finish !== '') {
                $finishReason = $finish;
            }
            $usage = $chunk['usage'] ?? $chunk['x_groq']['usage'] ?? null;
            if (is_array($usage)) {
                $inputTokens = (int) ($usage['prompt_tokens'] ?? $inputTokens);
                $outputTokens = (int) ($usage['completion_tokens'] ?? $outputTokens);
            }
        };

        // Read the PSR body in chunks and split into lines, so deltas are delivered as they arrive.
        // Only transport reads are wrapped in try/catch; exceptions from $onDelta propagate untouched.
        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $emptyReads = 0;
        while (true) {
            try {
                if ($body->eof()) {
                    break;
                }
                $data = $body->read(8192);
                $timedOut = $data === '' && ($body->getMetadata('timed_out') ?? false);
            } catch (\RuntimeException $e) {
                throw new LlmException(LlmException::UNAVAILABLE, 'stream interrupted: '.$e->getMessage());
            }
            if ($data === '') {
                if ($timedOut || ++$emptyReads >= 3) {
                    throw new LlmException(LlmException::UNAVAILABLE, 'stream stalled');
                }

                continue;
            }
            $emptyReads = 0;
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $handleLine(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
            }
        }
        $handleLine($buffer);

        if ($finishReason === null && ! $done) {
            throw new LlmException(LlmException::UNAVAILABLE, 'stream ended without finish_reason');
        }
        $finishReason ??= 'stop';

        if ($finishReason === 'content_filter') {
            throw new LlmException(LlmException::REFUSAL, 'content filtered');
        }
        if ($finishReason === 'length') {
            throw new LlmException(LlmException::TRUNCATED, 'hit max_tokens');
        }

        return new AnswerResult($text, $inputTokens, $outputTokens, $finishReason, $this->answerModel);
    }

    public function rewriteQuestion(string $system, string $user): string
    {
        $rewritten = trim($this->complete([
            'model' => $this->helperModel,
            'messages' => $this->messages($system, $user),
            'max_tokens' => $this->helperMaxTokens,
            'temperature' => 0.2,
        ]));
        if ($rewritten === '') {
            throw new LlmException(LlmException::UNAVAILABLE, 'empty rewrite');
        }

        return $rewritten;
    }

    public function checkGrounding(string $system, string $user): GroundingResult
    {
        $user .= "\n\nAnswer only with JSON: {\"grounded\": true|false, \"reason\": \"...\"}";
        $content = $this->complete([
            'model' => $this->helperModel,
            'messages' => $this->messages($system, $user),
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => $this->helperMaxTokens,
            'temperature' => 0.2,
        ]);

        $json = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)));
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new LlmException(LlmException::UNAVAILABLE, 'unparsable grounding output');
        }
        if (! is_array($data) || ! array_key_exists('grounded', $data)) {
            throw new LlmException(LlmException::UNAVAILABLE, 'unparsable grounding output');
        }

        $result = new GroundingResult;
        $result->grounded = filter_var($data['grounded'], FILTER_VALIDATE_BOOLEAN);
        $result->reason = is_scalar($data['reason'] ?? '') ? (string) ($data['reason'] ?? '') : 'unparsable reason';

        return $result;
    }

    /** @return list<array{role: string, content: string}> */
    private function messages(string $system, string $user): array
    {
        return [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]];
    }

    /** @param array<string, mixed> $payload */
    private function complete(array $payload): string
    {
        $response = $this->send($payload);
        $choice = $response->json('choices.0');
        if (is_array($choice) && ($choice['finish_reason'] ?? null) === 'content_filter') {
            throw new LlmException(LlmException::REFUSAL, 'content filtered');
        }
        $content = $choice['message']['content'] ?? null;
        if (! is_string($content)) {
            throw new LlmException(LlmException::UNAVAILABLE, 'no content in response');
        }

        return $content;
    }

    /** @param array<string, mixed> $payload */
    private function send(array $payload, bool $stream = false): Response
    {
        if ($this->apiKey === '') {
            throw new LlmException(LlmException::UNAVAILABLE, 'missing api key');
        }

        if ($this->reasoningEffort !== '') {
            $payload['reasoning_effort'] = $this->reasoningEffort;
        }

        try {
            $request = Http::withToken($this->apiKey)->timeout($this->timeout);
            if ($stream) {
                $request = $request->withOptions(['stream' => true]);
            }
            $response = $request->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new LlmException(LlmException::UNAVAILABLE, $e->getMessage());
        }

        if (! $response->successful()) {
            throw new LlmException(LlmException::UNAVAILABLE, $response->status().' '.mb_substr($response->body(), 0, 300));
        }

        return $response;
    }
}
