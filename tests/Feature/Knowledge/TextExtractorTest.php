<?php

use App\Knowledge\Extraction\CompositeTextExtractor;
use App\Knowledge\Extraction\ExtractionException;
use App\Knowledge\Extraction\HttpTextExtractor;
use App\Knowledge\Extraction\LocalTextExtractor;
use Illuminate\Support\Facades\Http;

it('reads markdown locally, strips BOM and CRLF', function () {
    $path = tempnam(sys_get_temp_dir(), 'md');
    file_put_contents($path, "\xEF\xBB\xBF# Заголовок\r\n\r\nАбзац\r\n");

    $pages = (new LocalTextExtractor)->extract($path, 'text/markdown');

    expect($pages)->toHaveCount(1)->and($pages[0]->text)->toBe("# Заголовок\n\nАбзац\n");
});

it('sends pdf to the sidecar and maps pages', function () {
    Http::fake(['embedder.invalid/extract-text' => Http::response(['pages' => [['page' => 1, 'text' => 'a'], ['page' => 2, 'text' => 'b']], 'meta' => ['pages_count' => 2]])]);
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, '%PDF-1.4');

    $pages = app(HttpTextExtractor::class)->extract($path, 'application/pdf');

    expect($pages)->toHaveCount(2)->and($pages[1]->page)->toBe(2)->and($pages[1]->text)->toBe('b');
});

it('throws UNSUPPORTED for unknown mime in composite', function () {
    $composite = new CompositeTextExtractor(new LocalTextExtractor);
    expect(fn () => $composite->extract('/tmp/x', 'image/png'))
        ->toThrow(ExtractionException::class, ExtractionException::UNSUPPORTED);
});
