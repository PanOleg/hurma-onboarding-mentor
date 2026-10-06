<?php

it('exposes rag config defaults', function () {
    expect(config('rag.top_k'))->toBe(8)
        ->and(config('rag.candidate_limit'))->toBe(100)
        ->and(config('rag.max_distance'))->toBe(0.35)
        ->and(config('rag.embedding_dim'))->toBe(384)
        ->and(config('rag.models.answer'))->toBe('claude-sonnet-5-5')
        ->and(config('rag.models.helper'))->toBe('claude-haiku-4-5');
});
