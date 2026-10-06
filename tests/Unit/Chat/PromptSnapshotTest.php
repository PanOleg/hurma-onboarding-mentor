<?php

use App\Chat\Llm\PromptBuilder;
use App\Chat\Llm\PromptLoader;
use App\Retrieval\SearchHit;

it('builds the answer prompt deterministically', function () {
    $builder = new PromptBuilder(new PromptLoader);
    $hits = [
        new SearchHit(55, 3, 'Політика відпусток', 2, 'Тривалість', 'Кожен працівник має 24 календарні дні відпустки.', 0.12),
        new SearchHit(56, 3, 'Політика відпусток', 3, null, 'Заявку подають за 14 днів.', 0.2),
    ];

    $prompt = $builder->answer('Скільки днів відпустки?', $hits);

    expect($prompt['system'])->toMatchSnapshot()
        ->and($prompt['user'])->toMatchSnapshot();
});

it('builds rewrite and grounding prompts deterministically', function () {
    $builder = new PromptBuilder(new PromptLoader);
    $hits = [new SearchHit(1, 1, 'Док', null, null, 'Текст фрагмента.', 0.1)];

    expect($builder->rewrite('а скільки для нових?', [['role' => 'user', 'content' => 'Скільки днів відпустки?'], ['role' => 'assistant', 'content' => '24 дні [1].']])['user'])->toMatchSnapshot()
        ->and($builder->grounding('24 дні [1].', $hits)['user'])->toMatchSnapshot();
});
