<?php

namespace App\Retrieval;

final readonly class SearchHit
{
    public function __construct(
        public int $chunkId,
        public int $documentId,
        public string $documentTitle,
        public ?int $page,
        public ?string $heading,
        public string $content,
        public float $distance,
    ) {}
}
