<?php

namespace App\Chat\Llm;

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

final class GroundingResult implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'true if every statement is supported by the fragments or the answer honestly says the info is missing')]
    public bool $grounded;

    #[Constrained(description: 'one sentence why')]
    public string $reason;
}
