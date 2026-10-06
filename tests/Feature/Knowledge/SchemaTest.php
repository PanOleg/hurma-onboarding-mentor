<?php

use App\Knowledge\Models\Department;
use App\Knowledge\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('stores and reads a vector in document_chunks', function () {
    $doc = Document::factory()->create();
    $vector = array_fill(0, 384, 0.0);
    $vector[0] = 1.0;
    DB::insert(
        'INSERT INTO document_chunks (document_id, position, content, token_count, embedding, created_at, updated_at)
         VALUES (?, ?, ?, ?, VEC_FromText(?), NOW(), NOW())',
        [$doc->id, 0, 'hello', 1, json_encode($vector)]
    );
    $distance = DB::selectOne(
        'SELECT VEC_DISTANCE_COSINE(embedding, VEC_FromText(?)) AS d FROM document_chunks WHERE document_id = ?',
        [json_encode($vector), $doc->id]
    )->d;
    expect((float) $distance)->toBeLessThan(0.0001);
});

it('applies audience rules in visibleTo', function () {
    $eng = Department::factory()->create();
    $mkt = Department::factory()->create();
    $user = User::factory()->inDepartment($eng, 'developer')->create();

    $all = Document::factory()->create(['title' => 'all']);
    $engDoc = Document::factory()->forDepartment($eng)->create(['title' => 'eng']);
    $mktDoc = Document::factory()->forDepartment($mkt)->create(['title' => 'mkt']);
    $devDoc = Document::factory()->forRole('developer')->create(['title' => 'dev']);
    $qaDoc = Document::factory()->forRole('qa')->create(['title' => 'qa']);

    $titles = Document::query()->visibleTo($user)->pluck('title')->sort()->values()->all();

    expect($titles)->toBe(['all', 'dev', 'eng']);
});

it('does not match department rule for a user without department', function () {
    $eng = Department::factory()->create();
    $user = User::factory()->create(['department_id' => null, 'job_role' => null]);
    Document::factory()->forDepartment($eng)->create();
    Document::factory()->forRole('qa')->create();

    expect(Document::query()->visibleTo($user)->count())->toBe(0);
});
