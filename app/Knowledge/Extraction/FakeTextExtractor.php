<?php

namespace App\Knowledge\Extraction;

use App\Knowledge\Contracts\TextExtractor;
use Throwable;

final class FakeTextExtractor implements TextExtractor
{
    /** @var array<string, list<ExtractedPage>> */
    public array $pagesByPath = [];

    public ?Throwable $throws = null;

    public function supports(string $mime): bool
    {
        return true;
    }

    public function extract(string $absolutePath, string $mime): array
    {
        if ($this->throws !== null) {
            throw $this->throws;
        }

        return $this->pagesByPath[$absolutePath] ?? [new ExtractedPage(1, (string) file_get_contents($absolutePath))];
    }
}
