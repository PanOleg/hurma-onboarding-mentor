<?php

use App\Knowledge\Embedding\FakeEmbeddingProvider;

it('returns deterministic normalized 384-dim vectors', function () {
    $p = new FakeEmbeddingProvider;
    $a = $p->embedQuery('відпустка');
    $b = $p->embedQuery('відпустка');
    $c = $p->embedQuery('зарплата');

    expect($a)->toHaveCount(384)->toBe($b)->not->toBe($c);
    $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $a)));
    expect(abs($norm - 1.0))->toBeLessThan(1e-6);
});

it('makes similar texts closer than unrelated ones', function () {
    $p = new FakeEmbeddingProvider;
    $cos = fn (array $x, array $y) => array_sum(array_map(fn ($a, $b) => $a * $b, $x, $y));
    $base = $p->embedQuery('політика відпусток: скільки днів відпустки');
    $near = $p->embedQuery('скільки днів відпустки');
    $far = $p->embedQuery('налаштування принтера на четвертому поверсі');

    expect($cos($base, $near))->toBeGreaterThan($cos($base, $far));
});
