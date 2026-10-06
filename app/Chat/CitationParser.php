<?php

namespace App\Chat;

use App\Retrieval\SearchHit;

final class CitationParser
{
    /**
     * @param  list<SearchHit>  $hits
     * @return array{citations: list<ParsedCitation>, invalidMarkers: list<int>}
     */
    public function parse(string $answer, array $hits): array
    {
        preg_match_all('/\[(\d{1,2})\]/u', $answer, $m);
        $seen = [];
        $citations = [];
        $invalid = [];
        foreach ($m[1] as $raw) {
            $n = (int) $raw;
            if ($n < 1 || $n > count($hits)) {
                if (! in_array($n, $invalid, true)) {
                    $invalid[] = $n;
                }

                continue;
            }
            if (isset($seen[$n])) {
                continue;
            }
            $seen[$n] = true;
            $citations[] = new ParsedCitation($n, $hits[$n - 1]);
        }

        return ['citations' => $citations, 'invalidMarkers' => $invalid];
    }
}
