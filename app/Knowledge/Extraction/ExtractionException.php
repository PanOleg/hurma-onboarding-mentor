<?php

namespace App\Knowledge\Extraction;

use RuntimeException;

final class ExtractionException extends RuntimeException
{
    public const UNAVAILABLE = 'extractor_unavailable';

    public const UNSUPPORTED = 'unsupported_format';

    public function __construct(public readonly string $code_, string $detail = '')
    {
        parent::__construct(trim("$code_ $detail"));
    }
}
