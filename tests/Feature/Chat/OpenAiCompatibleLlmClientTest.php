<?php

use App\Chat\Contracts\LlmClient;
use App\Chat\Llm\LlmException;
use App\Chat\Llm\OpenAiCompatibleLlmClient;
use App\Providers\RagServiceProvider;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

function compatClient(string $key = 'sk-test'): OpenAiCompatibleLlmClient
{
    return new OpenAiCompatibleLlmClient('https://llm.invalid/v1', $key, 'answer-model', 'helper-model', 5);
}

function sse(array $chunks): string
{
    $body = '';
    foreach ($chunks as $chunk) {
        $body .= 'data: '.json_encode($chunk, JSON_UNESCAPED_UNICODE)."\n\n";
    }

    return $body."data: [DONE]\n\n";
}

function completion(string $content): array
{
    return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']]];
}

it('streams tokens and reports usage', function () {
    Http::fake(['llm.invalid/v1/chat/completions' => Http::response(sse([
        ['choices' => [['delta' => ['content' => 'Так '], 'finish_reason' => null]]],
        ['choices' => [['delta' => ['content' => '[1].'], 'finish_reason' => null]]],
        ['choices' => [['delta' => [], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 5]],
    ]), 200, ['Content-Type' => 'text/event-stream'])]);

    $fragments = [];
    $result = compatClient()->streamAnswer('sys', 'usr', function (string $f) use (&$fragments) {
        $fragments[] = $f;
    });

    expect($fragments)->toBe(['Так ', '[1].'])
        ->and($result->text)->toBe('Так [1].')
        ->and($result->inputTokens)->toBe(100)
        ->and($result->outputTokens)->toBe(5)
        ->and($result->stopReason)->toBe('stop')
        ->and($result->model)->toBe('answer-model');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://llm.invalid/v1/chat/completions'
        && $r->hasHeader('Authorization', 'Bearer sk-test')
        && $r['stream'] === true
        && $r['stream_options'] === ['include_usage' => true]
        && $r['model'] === 'answer-model'
        && $r['messages'][0] === ['role' => 'system', 'content' => 'sys']
        && $r['messages'][1] === ['role' => 'user', 'content' => 'usr']);
});

it('falls back to x_groq usage', function () {
    Http::fake(['llm.invalid/*' => Http::response(sse([
        ['choices' => [['delta' => ['content' => 'ok'], 'finish_reason' => null]]],
        ['choices' => [['delta' => [], 'finish_reason' => 'stop']], 'x_groq' => ['usage' => ['prompt_tokens' => 7, 'completion_tokens' => 2]]],
    ]))]);

    $result = compatClient()->streamAnswer('s', 'u', fn () => null);

    expect($result->inputTokens)->toBe(7)->and($result->outputTokens)->toBe(2);
});

it('rewrites a question with trimmed content on the helper model', function () {
    Http::fake(['llm.invalid/*' => Http::response(completion("  Скільки днів відпустки?\n"))]);

    expect(compatClient()->rewriteQuestion('sys', 'usr'))->toBe('Скільки днів відпустки?');
    Http::assertSent(fn (Request $r) => $r['model'] === 'helper-model' && $r['max_tokens'] === 1024 && ! isset($r['stream']));
});

it('parses grounding json', function () {
    Http::fake(['llm.invalid/*' => Http::response(completion('{"grounded": false, "reason": "x"}'))]);

    $result = compatClient()->checkGrounding('sys', 'usr');

    expect($result->grounded)->toBeFalse()->and($result->reason)->toBe('x');
    Http::assertSent(fn (Request $r) => $r['response_format'] === ['type' => 'json_object'] && $r['model'] === 'helper-model');
});

it('tolerates code fences in grounding output', function () {
    Http::fake(['llm.invalid/*' => Http::response(completion("```json\n{\"grounded\": true, \"reason\": \"ok\"}\n```"))]);

    $result = compatClient()->checkGrounding('sys', 'usr');

    expect($result->grounded)->toBeTrue()->and($result->reason)->toBe('ok');
});

it('throws UNAVAILABLE on unparsable grounding output', function () {
    Http::fake(['llm.invalid/*' => Http::response(completion('not json'))]);

    expect(fn () => compatClient()->checkGrounding('s', 'u'))->toThrow(LlmException::class, LlmException::UNAVAILABLE);
});

it('throws TRUNCATED when finish_reason is length', function () {
    Http::fake(['llm.invalid/*' => Http::response(sse([
        ['choices' => [['delta' => ['content' => 'abc'], 'finish_reason' => 'length']]],
    ]))]);

    expect(fn () => compatClient()->streamAnswer('s', 'u', fn () => null))
        ->toThrow(LlmException::class, LlmException::TRUNCATED);
});

it('throws REFUSAL on content_filter', function () {
    Http::fake(['llm.invalid/*' => Http::response(sse([
        ['choices' => [['delta' => [], 'finish_reason' => 'content_filter']]],
    ]))]);

    expect(fn () => compatClient()->streamAnswer('s', 'u', fn () => null))
        ->toThrow(LlmException::class, LlmException::REFUSAL);
});

it('throws UNAVAILABLE on HTTP 429 without retrying', function () {
    Http::fake(['llm.invalid/*' => Http::response('{"error":"rate limited"}', 429)]);

    expect(fn () => compatClient()->rewriteQuestion('s', 'u'))->toThrow(LlmException::class, LlmException::UNAVAILABLE);
    expect(fn () => compatClient()->streamAnswer('s', 'u', fn () => null))->toThrow(LlmException::class, '429');
    Http::assertSentCount(2);
});

it('throws UNAVAILABLE on connection errors', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    expect(fn () => compatClient()->rewriteQuestion('s', 'u'))->toThrow(LlmException::class, LlmException::UNAVAILABLE);
});

it('throws UNAVAILABLE without a request when the api key is empty', function () {
    Http::fake();

    expect(fn () => compatClient('')->streamAnswer('s', 'u', fn () => null))
        ->toThrow(LlmException::class, 'missing api key');
    Http::assertNothingSent();
});

it('binds the openai compatible client when the driver is selected', function () {
    config(['rag.llm.driver' => 'openai_compatible']);
    $this->app->offsetUnset(LlmClient::class);
    $this->app->register(RagServiceProvider::class, true);

    expect(app(LlmClient::class))->toBeInstanceOf(OpenAiCompatibleLlmClient::class);
});

it('throws on an unknown driver', function () {
    config(['rag.llm.driver' => 'nope']);
    $this->app->offsetUnset(LlmClient::class);
    $this->app->register(RagServiceProvider::class, true);

    expect(fn () => app(LlmClient::class))->toThrow(InvalidArgumentException::class);
});

function runStream(string $body): array
{
    Http::fake(['llm.invalid/*' => Http::response($body)]);
    $fragments = [];
    $result = compatClient()->streamAnswer('s', 'u', function (string $f) use (&$fragments) {
        $fragments[] = $f;
    });

    return [$result, $fragments];
}

it('throws UNAVAILABLE on an in-stream error payload', function () {
    $body = sse([
        ['choices' => [['delta' => ['content' => 'a'], 'finish_reason' => null]]],
        ['error' => ['message' => 'overloaded', 'type' => 'server_error']],
    ]);
    Http::fake(['llm.invalid/*' => Http::response($body)]);

    expect(fn () => compatClient()->streamAnswer('s', 'u', fn () => null))
        ->toThrow(LlmException::class, 'overloaded');
});

it('throws UNAVAILABLE when the stream ends without finish_reason or DONE', function () {
    Http::fake(['llm.invalid/*' => Http::response('data: '.json_encode(['choices' => [['delta' => ['content' => 'a']]]])."\n\n")]);

    expect(fn () => compatClient()->streamAnswer('s', 'u', fn () => null))
        ->toThrow(LlmException::class, LlmException::UNAVAILABLE);
});

it('throws UNAVAILABLE when the stream stalls', function () {
    $stalled = new class(Utils::streamFor('')) implements StreamInterface
    {
        use StreamDecoratorTrait;

        public function eof(): bool
        {
            return false;
        }

        public function read($length): string
        {
            return '';
        }

        public function getMetadata($key = null)
        {
            return $key === 'timed_out' ? true : null;
        }
    };
    Http::fake(['llm.invalid/*' => Create::promiseFor(new Response(200, [], $stalled))]);

    expect(fn () => compatClient()->streamAnswer('s', 'u', fn () => null))
        ->toThrow(LlmException::class, 'stream stalled');
});

it('stops on repeated empty reads even without a timed_out flag', function () {
    $stalled = new class(Utils::streamFor('')) implements StreamInterface
    {
        use StreamDecoratorTrait;

        public function eof(): bool
        {
            return false;
        }

        public function read($length): string
        {
            return '';
        }
    };
    Http::fake(['llm.invalid/*' => Create::promiseFor(new Response(200, [], $stalled))]);

    expect(fn () => compatClient()->streamAnswer('s', 'u', fn () => null))
        ->toThrow(LlmException::class, 'stream stalled');
});

it('does not relabel exceptions thrown by the delta callback', function () {
    Http::fake(['llm.invalid/*' => Http::response(sse([['choices' => [['delta' => ['content' => 'a']]]]]))]);

    expect(fn () => compatClient()->streamAnswer('s', 'u', fn () => throw new DomainException('boom')))
        ->toThrow(DomainException::class, 'boom');
});

it('reassembles lines that straddle the 8192-byte read boundary', function () {
    $expected = '';
    $chunks = [['choices' => [['delta' => ['content' => str_repeat('я', 9000)], 'finish_reason' => null]]]];
    $expected .= str_repeat('я', 9000);
    for ($i = 0; $i < 600; $i++) {
        $chunks[] = ['choices' => [['delta' => ['content' => "w$i "], 'finish_reason' => null]]];
        $expected .= "w$i ";
    }
    $chunks[] = ['choices' => [['delta' => [], 'finish_reason' => 'stop']]];

    [$result, $fragments] = runStream(sse($chunks));

    expect($result->text)->toBe($expected)->and(implode('', $fragments))->toBe($expected);
});

it('handles CRLF endings, usage-only chunks, null content, reasoning deltas and a trailing line without newline', function () {
    $lines = [
        'data: '.json_encode(['choices' => [['delta' => ['reasoning' => 'thinking...'], 'finish_reason' => null]]]),
        'data: '.json_encode(['choices' => [['delta' => ['content' => null], 'finish_reason' => null]]]),
        'data: '.json_encode(['choices' => [['delta' => ['content' => 'Ok'], 'finish_reason' => null]]]),
        'data: '.json_encode(['choices' => [['delta' => [], 'finish_reason' => 'stop']]]),
        'data: '.json_encode(['choices' => [], 'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 3]]),
    ];
    // Last data line has no trailing newline and there is no [DONE]; finish_reason was seen.
    [$result, $fragments] = runStream(implode("\r\n\r\n", $lines));

    expect($fragments)->toBe(['Ok'])
        ->and($result->text)->toBe('Ok')
        ->and($result->inputTokens)->toBe(11)
        ->and($result->outputTokens)->toBe(3)
        ->and($result->stopReason)->toBe('stop');
});

it('accepts a trailing data line without newline right before DONE-less end after DONE', function () {
    $body = 'data: '.json_encode(['choices' => [['delta' => ['content' => 'x'], 'finish_reason' => 'stop']]])."\n\ndata: [DONE]";

    [$result] = runStream($body);

    expect($result->text)->toBe('x');
});

it('reports an unparsable grounding reason', function () {
    Http::fake(['llm.invalid/*' => Http::response(completion('{"grounded": true, "reason": {"a": 1}}'))]);

    expect(compatClient()->checkGrounding('s', 'u')->reason)->toBe('unparsable reason');
});

it('sends reasoning_effort and helper max tokens, and fails on an empty rewrite', function () {
    Http::fake(['llm.invalid/*' => Http::response(completion('  '))]);
    $client = new OpenAiCompatibleLlmClient('https://llm.invalid/v1', 'k', 'a', 'h', 5, 'low', 1024);

    expect(fn () => $client->rewriteQuestion('s', 'u'))->toThrow(LlmException::class, 'empty rewrite');
    Http::assertSent(fn (Request $r) => $r['reasoning_effort'] === 'low' && $r['max_tokens'] === 1024);
});
