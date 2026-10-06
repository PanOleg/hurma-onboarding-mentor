<?php

namespace App\Knowledge\Embedding;

use RuntimeException;

final class EmbeddingException extends RuntimeException
{
    public const UNAVAILABLE = 'embedder_unavailable';

    public const BAD_DIMENSION = 'embedder_bad_dimension';

    public function __construct(public readonly string $code_, string $detail = '')
    {
        parent::__construct(trim("$code_ $detail"));
    }
}
