<?php

return [
    'top_k' => (int) env('RAG_TOP_K', 8),
    'candidate_limit' => (int) env('RAG_CANDIDATE_LIMIT', 100),
    'max_distance' => (float) env('RAG_MAX_DISTANCE', 0.35),
    'embedding_dim' => 384,
    'chunk' => [
        'target_tokens' => 400,
        'overlap_tokens' => 60,
        'max_tokens' => 600,
    ],
    'embedder' => [
        'url' => env('EMBEDDER_URL', 'http://127.0.0.1:8100'),
        'timeout_embed' => 30,
        'timeout_extract' => 120,
        'batch_size' => 32,
    ],
    'models' => [
        'answer' => env('RAG_MODEL_ANSWER', 'claude-sonnet-5-5'),
        'helper' => env('RAG_MODEL_HELPER', 'claude-haiku-4-5'),
    ],
    'prompts' => [
        'answer' => 'answer.v1',
        'rewrite' => 'rewrite.v1',
        'grounding' => 'grounding.v1',
    ],
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],
    'upload' => [
        'max_bytes' => 20 * 1024 * 1024,
        'mimes' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/markdown', 'text/plain'],
    ],
];
