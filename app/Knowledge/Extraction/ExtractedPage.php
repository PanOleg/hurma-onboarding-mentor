<?php

namespace App\Knowledge\Extraction;

final readonly class ExtractedPage
{
    public function __construct(public int $page, public string $text) {}
}
