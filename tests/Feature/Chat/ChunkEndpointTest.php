<?php

use App\Knowledge\Chunking\Chunk;
use App\Knowledge\Embedding\FakeEmbeddingProvider;
use App\Knowledge\Ingestion\ChunkWriter;
use App\Knowledge\Models\Department;
use App\Knowledge\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('returns 404 for a chunk of a document outside the audience and 200 for an all-audience one', function () {
    $insert = function (Document $doc, string $text): int {
        $fake = app(FakeEmbeddingProvider::class);
        app(ChunkWriter::class)->insertBatch($doc, [new Chunk(0, 1, null, $text, 10)], $fake->embedPassages([$text]));

        return (int) DB::table('document_chunks')->where('document_id', $doc->id)->value('id');
    };
    $a = Department::factory()->create();
    $b = Department::factory()->create();
    $user = User::factory()->inDepartment($a, 'developer')->create();
    $foreign = $insert(Document::factory()->forDepartment($b)->create(), 'закритий текст відділу B');
    $open = $insert(Document::factory()->create(), 'загальний текст для всіх');

    $this->actingAs($user)->getJson("/api/v1/chunks/{$foreign}")
        ->assertNotFound()->assertJsonPath('error.code', 'not_found');
    $this->actingAs($user)->getJson("/api/v1/chunks/{$open}")
        ->assertOk()->assertJsonPath('data.content', 'загальний текст для всіх');
});
