<?php

namespace App\Knowledge\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Extracting = 'extracting';
    case Chunking = 'chunking';
    case Embedding = 'embedding';
    case Ready = 'ready';
    case Failed = 'failed';

    /** Order in the pipeline; used by jobs for idempotency checks. */
    public function order(): int
    {
        return match ($this) {
            self::Uploaded => 0, self::Extracting => 1, self::Chunking => 2,
            self::Embedding => 3, self::Ready => 4, self::Failed => 99,
        };
    }
}
