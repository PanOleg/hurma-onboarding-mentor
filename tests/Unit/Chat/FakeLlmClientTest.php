<?php

use App\Chat\Llm\FakeLlmClient;
use App\Chat\Llm\LlmException;

it('streams the configured answer word by word and records the call', function () {
    $fake = new FakeLlmClient;
    $fake->nextAnswer = 'Один два [1].';
    $deltas = [];

    $result = $fake->streamAnswer('sys', 'usr', function (string $d) use (&$deltas) {
        $deltas[] = $d;
    });

    expect(implode('', $deltas))->toBe('Один два [1].')
        ->and(count($deltas))->toBeGreaterThan(1)
        ->and($result->text)->toBe('Один два [1].')
        ->and($result->stopReason)->toBe('end_turn')
        ->and($fake->calls['streamAnswer'][0]['user'])->toBe('usr');
});

it('throws the configured exception', function () {
    $fake = new FakeLlmClient;
    $fake->throws = new LlmException(LlmException::UNAVAILABLE, 'down');
    expect(fn () => $fake->streamAnswer('s', 'u', fn () => null))->toThrow(LlmException::class);
});
