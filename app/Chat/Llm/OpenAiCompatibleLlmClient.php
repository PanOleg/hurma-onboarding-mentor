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
        $finishReason = 'stop';

        $handleLine = function (string $line) use (&$text, &$inputTokens, &$outputTokens, &$finishReason, $onDelta): void {
            $line = trim($line);
            if (! str_starts_with($line, 'data:')) {
                return;
            }
            $payload = trim(substr($line, 5));
            if ($payload === '' || $payload === '[DONE]') {
                return;
            }
            $chunk = json_decode($payload, true);
            if (! is_array($chunk)) {
                return;
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

        try {
            // Read the PSR body in chunks and split into lines, so deltas are delivered as they arrive.
            $body = $response->toPsrResponse()->getBody();
            $buffer = '';
            while (! $body->eof()) {
                $buffer .= $body->read(8192);
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $handleLine(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);
                }
            }
            $handleLine($buffer);
        } catch (\RuntimeException $e) {
            throw new LlmException(LlmException::UNAVAILABLE, 'stream interrupted: '.$e->getMessage());
        }

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
        return trim($this->complete([
            'model' => $this->helperModel,
            'messages' => $this->messages($system, $user),
            'max_tokens' => 256,
            'temperature' => 0.2,
        ]));
    }

    public function checkGrounding(string $system, string $user): GroundingResult
    {
        $user .= "\n\nAnswer only with JSON: {\"grounded\": true|false, \"reason\": \"...\"}";
        $content = $this->complete([
            'model' => $this->helperModel,
            'messages' => $this->messages($system, $user),
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 256,
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
        $result->reason = (string) ($data['reason'] ?? '');

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
