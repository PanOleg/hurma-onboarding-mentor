<?php

namespace App\Knowledge\Contracts;

interface EmbeddingProvider
{
    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedPassages(array $texts): array;

    /** @return list<float> */
    public function embedQuery(string $text): array;
}
