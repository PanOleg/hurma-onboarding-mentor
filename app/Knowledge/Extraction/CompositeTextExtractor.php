<?php

namespace App\Knowledge\Extraction;

use App\Knowledge\Contracts\TextExtractor;

final class CompositeTextExtractor implements TextExtractor
{
    /** @var list<TextExtractor> */
    private array $extractors;

    public function __construct(TextExtractor ...$extractors)
    {
        $this->extractors = array_values($extractors);
    }

    public function supports(string $mime): bool
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mime)) {
                return true;
            }
        }

        return false;
    }

    public function extract(string $absolutePath, string $mime): array
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mime)) {
                return $extractor->extract($absolutePath, $mime);
            }
        }

        throw new ExtractionException(ExtractionException::UNSUPPORTED, $mime);
    }
}
