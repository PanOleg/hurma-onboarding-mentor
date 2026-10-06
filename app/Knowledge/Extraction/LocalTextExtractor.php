<?php

namespace App\Knowledge\Extraction;

use App\Knowledge\Contracts\TextExtractor;

final class LocalTextExtractor implements TextExtractor
{
    public function supports(string $mime): bool
    {
        return in_array($mime, ['text/markdown', 'text/plain'], true);
    }

    public function extract(string $absolutePath, string $mime): array
    {
        $raw = file_get_contents($absolutePath);
        if ($raw === false) {
            throw new ExtractionException(ExtractionException::UNAVAILABLE, 'cannot read file');
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        return [new ExtractedPage(1, str_replace("\r\n", "\n", $raw))];
    }
}
