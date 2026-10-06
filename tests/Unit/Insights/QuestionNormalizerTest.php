<?php

use App\Insights\QuestionNormalizer;

it('normalizes case, whitespace and trailing punctuation', function () {
    $n = new QuestionNormalizer;
    expect($n->normalize('  Скільки  ДНІВ відпустки ?? '))->toBe('скільки днів відпустки')
        ->and($n->normalize('Що з лікарняними.'))->toBe('що з лікарняними')
        ->and(mb_strlen($n->normalize(str_repeat('а', 400))))->toBe(255);
});
