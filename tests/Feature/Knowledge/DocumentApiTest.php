<?php

use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Jobs\ChunkDocument;
use App\Knowledge\Jobs\EmbedDocumentChunks;
use App\Knowledge\Jobs\ExtractDocumentText;
use App\Knowledge\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->hr = User::factory()->hrAdmin()->create();
});

it('uploads a markdown document, stores it by sha256 and dispatches the chain (AC-1)', function () {
    Bus::fake();
    $file = UploadedFile::fake()->createWithContent('policy.md', "# Відпустки\n\n24 дні.");

    $res = $this->actingAs($this->hr)->postJson('/api/v1/documents', [
        'file' => $file, 'title' => 'Політика відпусток', 'audience_type' => 'all',
    ]);

    $res->assertStatus(202)->assertJsonPath('data.status', 'uploaded')->assertJsonPath('data.title', 'Політика відпусток');
    $doc = Document::first();
    expect($doc->sha256)->toBe(hash('sha256', "# Відпустки\n\n24 дні."))
        ->and($doc->storage_path)->toBe("knowledge/{$doc->sha256}.md");
    Storage::disk('local')->assertExists($doc->storage_path);
    Bus::assertChained([ExtractDocumentText::class, ChunkDocument::class, EmbedDocumentChunks::class]);
});

it('returns the existing document with 200 for a duplicate sha256 (AC-2)', function () {
    Bus::fake();
    $file = fn () => UploadedFile::fake()->createWithContent('a.md', 'same content');
    $this->actingAs($this->hr)->postJson('/api/v1/documents', ['file' => $file(), 'title' => 'A', 'audience_type' => 'all'])->assertStatus(202);

    $this->actingAs($this->hr)->postJson('/api/v1/documents', ['file' => $file(), 'title' => 'B', 'audience_type' => 'all'])
        ->assertOk()->assertJsonPath('data.title', 'A');

    expect(Document::count())->toBe(1);
    Bus::assertDispatchedTimes(ExtractDocumentText::class, 1);
});

it('requires audience_value for department and role', function () {
    $file = UploadedFile::fake()->createWithContent('a.md', 'x');
    $this->actingAs($this->hr)->postJson('/api/v1/documents', ['file' => $file, 'title' => 'A', 'audience_type' => 'department'])
        ->assertStatus(422)->assertJsonPath('error.details.audience_value.0', fn ($m) => is_string($m));
});

it('rejects unsupported mime and oversized files', function () {
    $png = UploadedFile::fake()->image('x.png');
    $this->actingAs($this->hr)->postJson('/api/v1/documents', ['file' => $png, 'title' => 'A', 'audience_type' => 'all'])->assertStatus(422);

    $big = UploadedFile::fake()->create('big.pdf', 21 * 1024, 'application/pdf');
    $this->actingAs($this->hr)->postJson('/api/v1/documents', ['file' => $big, 'title' => 'A', 'audience_type' => 'all'])->assertStatus(422);
});

it('forbids employees from knowledge endpoints', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee)->getJson('/api/v1/documents')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
    $this->actingAs($employee)->postJson('/api/v1/documents', [])->assertForbidden();
});

it('lists documents with status filter and shows one with ingestion runs', function () {
    Document::factory()->count(2)->create(['uploaded_by' => $this->hr->id]);
    $failed = Document::factory()->status(DocumentStatus::Failed)->create(['uploaded_by' => $this->hr->id]);
    $failed->ingestionRuns()->create(['step' => 'extract', 'status' => 'failed', 'attempt' => 3, 'started_at' => now(), 'finished_at' => now(), 'error' => 'down']);

    $this->actingAs($this->hr)->getJson('/api/v1/documents?status=failed')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($this->hr)->getJson("/api/v1/documents/{$failed->id}")->assertOk()
        ->assertJsonPath('data.ingestion_runs.0.step', 'extract')
        ->assertJsonPath('data.ingestion_runs.0.error', 'down');
});
