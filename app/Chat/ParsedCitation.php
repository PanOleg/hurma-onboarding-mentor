<?php

namespace App\Chat;

use App\Retrieval\SearchHit;

final readonly class ParsedCitation
{
    public function __construct(public int $marker, public SearchHit $hit) {}
}
