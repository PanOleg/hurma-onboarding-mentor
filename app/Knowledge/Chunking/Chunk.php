<?php

namespace App\Knowledge\Chunking;

final readonly class Chunk
{
    public function __construct(
        public int $position,
        public ?int $page,
        public ?string $heading,
        public string $content,
        public int $tokenCount,
    ) {}
}
