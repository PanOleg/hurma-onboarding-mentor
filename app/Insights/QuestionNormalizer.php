<?php

namespace App\Insights;

final class QuestionNormalizer
{
    public function normalize(string $q): string
    {
        $q = mb_strtolower(trim($q));
        $q = (string) preg_replace('/\s+/u', ' ', $q);
        $q = (string) preg_replace('/[\s?!.,;:…]+$/u', '', $q);

        return mb_substr($q, 0, 255);
    }
}
