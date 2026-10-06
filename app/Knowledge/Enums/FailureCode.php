<?php

namespace App\Knowledge\Enums;

enum FailureCode: string
{
    case NoTextLayer = 'no_text_layer';
    case ExtractorUnavailable = 'extractor_unavailable';
    case EmbedderUnavailable = 'embedder_unavailable';
    case UnsupportedFormat = 'unsupported_format';
    case Unknown = 'unknown';
}
