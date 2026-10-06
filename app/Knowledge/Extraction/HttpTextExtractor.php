<?php

namespace App\Knowledge\Extraction;

use App\Knowledge\Contracts\TextExtractor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class HttpTextExtractor implements TextExtractor
{
    private const MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function supports(string $mime): bool
    {
        return in_array($mime, self::MIMES, true);
    }

    public function extract(string $absolutePath, string $mime): array
    {
        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new ExtractionException(ExtractionException::UNAVAILABLE, 'cannot read file');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->attach('file', $contents, basename($absolutePath), ['Content-Type' => $mime])
                ->post(rtrim($this->baseUrl, '/').'/extract-text');
        } catch (ConnectionException $e) {
            throw new ExtractionException(ExtractionException::UNAVAILABLE, $e->getMessage());
        }

        if ($response->status() === 422) {
            throw new ExtractionException(ExtractionException::UNSUPPORTED, (string) $response->json('detail', ''));
        }
        if ($response->failed()) {
            throw new ExtractionException(ExtractionException::UNAVAILABLE, 'HTTP '.$response->status());
        }

        $pages = $response->json('pages');
        if (! is_array($pages)) {
            throw new ExtractionException(ExtractionException::UNAVAILABLE, 'malformed response');
        }

        return array_values(array_map(
            fn (array $p) => new ExtractedPage((int) $p['page'], (string) $p['text']),
            $pages,
        ));
    }
}
