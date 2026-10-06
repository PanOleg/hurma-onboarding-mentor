<?php

namespace App\Knowledge\Contracts;

use App\Knowledge\Extraction\ExtractedPage;

interface TextExtractor
{
    public function supports(string $mime): bool;

    /** @return list<ExtractedPage> */
    public function extract(string $absolutePath, string $mime): array;
}
