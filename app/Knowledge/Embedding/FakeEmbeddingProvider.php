<?php

namespace App\Knowledge\Embedding;

use App\Knowledge\Contracts\EmbeddingProvider;

final class FakeEmbeddingProvider implements EmbeddingProvider
{
    public const DIM = 384;

    public function embedPassages(array $texts): array
    {
        return array_map(fn (string $t) => $this->vectorFor($t), $texts);
    }

    public function embedQuery(string $text): array
    {
        return $this->vectorFor($text);
    }

    /** @return list<float> */
    public function vectorFor(string $text): array
    {
        $vector = array_fill(0, self::DIM, 0.0);
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $word) {
            $hash = crc32($word);
            $vector[$hash % self::DIM] += 1.0;
            $vector[($hash >> 8) % self::DIM] += 0.5;
        }
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vector)));
        if ($norm == 0.0) {
            $vector[0] = 1.0;

            return $vector;
        }

        return array_map(fn ($x) => $x / $norm, $vector);
    }
}
