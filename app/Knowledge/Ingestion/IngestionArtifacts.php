<?php

namespace App\Knowledge\Ingestion;

use App\Knowledge\Models\Document;

final class IngestionArtifacts
{
    public static function pagesPath(Document $d): string
    {
        return "knowledge/{$d->sha256}.pages.json";
    }

    public static function chunksPath(Document $d): string
    {
        return "knowledge/{$d->sha256}.chunks.json";
    }
}
