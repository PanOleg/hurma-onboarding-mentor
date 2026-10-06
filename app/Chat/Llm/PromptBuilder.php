<?php

namespace App\Chat\Llm;

use App\Retrieval\SearchHit;

final class PromptBuilder
{
    public function __construct(private readonly PromptLoader $loader) {}

    /**
     * @param  list<SearchHit>  $hits
     * @return array{system: string, user: string}
     */
    public function answer(string $question, array $hits): array
    {
        return ['system' => $this->loader->load('answer'), 'user' => "Фрагменти:\n".$this->fragments($hits)."\n\nПитання: $question"];
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{system: string, user: string}
     */
    public function rewrite(string $question, array $history): array
    {
        $lines = array_map(fn (array $m) => ($m['role'] === 'user' ? 'Користувач' : 'Наставник').': '.$m['content'], $history);

        return ['system' => $this->loader->load('rewrite'), 'user' => "Історія:\n".implode("\n", $lines)."\n\nОстаннє питання: $question"];
    }

    /**
     * @param  list<SearchHit>  $hits
     * @return array{system: string, user: string}
     */
    public function grounding(string $answer, array $hits): array
    {
        return ['system' => $this->loader->load('grounding'), 'user' => "Фрагменти:\n".$this->fragments($hits)."\n\nВідповідь:\n$answer"];
    }

    /** @param list<SearchHit> $hits */
    private function fragments(array $hits): string
    {
        $out = [];
        foreach ($hits as $i => $hit) {
            $where = $hit->documentTitle.($hit->page !== null ? ", стор. {$hit->page}" : '').($hit->heading !== null ? ", «{$hit->heading}»" : '');
            $out[] = '['.($i + 1)."] $where: ".trim($hit->content);
        }

        return implode("\n\n", $out);
    }
}
