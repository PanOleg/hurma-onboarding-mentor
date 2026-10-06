<?php

namespace App\Knowledge\Enums;

enum IngestionStep: string
{
    case Extract = 'extract';
    case Chunk = 'chunk';
    case Embed = 'embed';
}
