<?php

namespace App\Chat\Llm;

use RuntimeException;

final class LlmException extends RuntimeException
{
    public const UNAVAILABLE = 'llm_unavailable';

    public const REFUSAL = 'llm_refusal';

    public const TRUNCATED = 'llm_truncated';

    public function __construct(public readonly string $code_, string $detail = '')
    {
        parent::__construct($detail === '' ? $code_ : "$code_: $detail");
    }
}
