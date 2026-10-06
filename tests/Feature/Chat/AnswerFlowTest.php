<?php

use App\Chat\Enums\MessageStatus;
use App\Chat\Llm\FakeLlmClient;
use App\Chat\Llm\LlmException;
use App\Chat\Models\Conversation;
use App\Chat\Models\Message;
use App\Insights\Models\KnowledgeGap;
use App\Knowledge\Chunking\Chunk;
use App\Knowledge\Embedding\FakeEmbeddingProvider;
use App\Knowledge\Ingestion\ChunkWriter;
use App\Knowledge\Models\Document;
use App\Models\User;

function seedKnowledge(array $texts, string $title = 'Політика відпусток'): Document
{
    $doc = Document::factory()->create(['title' => $title]);
    $chunks = [];
    foreach ($texts as $i => $t) {
        $chunks[] = new Chunk($i, 1, null, $t, 10);
    }
    app(ChunkWriter::class)->insertBatch($doc, $chunks, app(FakeEmbeddingProvider::class)->embedPassages($texts));

    return $doc;
}

/** @return list<array{event: string, data: array}> */
function sseEvents(string $body): array
{
    $events = [];
    foreach (preg_split("/\n\n+/", trim($body)) as $frame) {
        if (! preg_match('/^event: (\w+)\ndata: (.*)$/s', $frame, $m)) {
            continue;
        }
        $events[] = ['event' => $m[1], 'data' => json_decode($m[2], true)];
    }

    return $events;
}

beforeEach(function () {
    // FakeEmbeddingProvider is a bag-of-words vector, so related texts land around distance 0.3-0.5.
    config(['rag.max_distance' => 0.6]);
    $this->user = User::factory()->create();
    $this->conversation = Conversation::factory()->create(['user_id' => $this->user->id]);
});

it('streams message, tokens, citations and done, and persists citations (AC-6, AC-8)', function () {
    seedKnowledge(['Скільки днів відпустки має працівник: 24 дні.', 'Заявку подають за 14 днів.']);
    app(FakeLlmClient::class)->nextAnswer = 'Працівник має 24 дні відпустки [1]. Заявка за 14 днів [2].';

    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'Скільки днів відпустки?']);

    $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
    $events = sseEvents($response->streamedContent());
    $names = array_map(fn ($e) => $e['event'], $events);

    expect($names[0])->toBe('message')
        ->and($names[1])->toBe('token')
        ->and(array_slice($names, -2))->toBe(['citations', 'done'])
        ->and(implode('', array_map(fn ($e) => $e['data']['text'], array_filter($events, fn ($e) => $e['event'] === 'token'))))
        ->toBe('Працівник має 24 дні відпустки [1]. Заявка за 14 днів [2].');

    $citations = $events[count($events) - 2]['data'];
    expect($citations)->toHaveCount(2)->and($citations[0]['marker'])->toBe(1)->and($citations[0]['document_title'])->toBe('Політика відпусток');

    $assistant = Message::where('role', 'assistant')->first();
    expect($assistant->status)->toBe(MessageStatus::Completed)
        ->and($assistant->grounded)->toBeTrue()
        ->and($assistant->citations)->toHaveCount(2)
        ->and($assistant->citations[0]->chunk_id)->toBe($citations[0]['chunk_id'])
        ->and($assistant->input_tokens)->toBe(1000)
        ->and($events[count($events) - 1]['data']['status'])->toBe('completed');
    expect(Message::where('role', 'user')->value('content'))->toBe('Скільки днів відпустки?');
});

it('returns no_answer without calling the answer model when nothing is relevant (AC-7)', function () {
    seedKnowledge(['Налаштування принтера на четвертому поверсі.']);
    config(['rag.max_distance' => 0.2]);

    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'Скільки днів відпустки?']);

    $events = sseEvents($response->streamedContent());
    expect(array_map(fn ($e) => $e['event'], $events))->toBe(['message', 'token', 'citations', 'done'])
        ->and($events[3]['data']['status'])->toBe('no_answer')
        ->and(app(FakeLlmClient::class)->calls)->not->toHaveKey('streamAnswer');
    expect(Message::where('role', 'assistant')->value('status'))->toBe(MessageStatus::NoAnswer);
    $gap = KnowledgeGap::first();
    expect($gap->question_normalized)->toBe('скільки днів відпустки')->and($gap->occurrences)->toBe(1)->and($gap->status)->toBe('open');

    $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'скільки днів відпустки'])->streamedContent();
    expect(KnowledgeGap::count())->toBe(1)->and(KnowledgeGap::first()->occurrences)->toBe(2);
});

it('also returns no_answer when there are no chunks at all', function () {
    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'Є питання?']);
    $events = sseEvents($response->streamedContent());
    expect(end($events)['data']['status'])->toBe('no_answer');
});

it('drops out-of-range markers and marks not grounded when no valid citation remains', function () {
    seedKnowledge(['Кожен працівник має 24 дні відпустки.']);
    app(FakeLlmClient::class)->nextAnswer = 'Відпустка 30 днів [7].';

    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'дні відпустки працівник']);

    $events = sseEvents($response->streamedContent());
    $citations = $events[count($events) - 2]['data'];
    expect($citations)->toBe([]);
    $assistant = Message::where('role', 'assistant')->first();
    expect($assistant->grounded)->toBeFalse()->and($assistant->citations)->toHaveCount(0);
    expect(KnowledgeGap::first()->status)->toBe('needs_review');
});

it('records needs_review when the grounding check fails', function () {
    seedKnowledge(['Кожен працівник має 24 дні відпустки.']);
    $fake = app(FakeLlmClient::class);
    $fake->nextAnswer = 'Відпустка 24 дні [1].';
    $fake->nextGrounded = false;

    $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'дні відпустки працівник'])->streamedContent();

    expect(Message::where('role', 'assistant')->value('grounded'))->toBeFalse()
        ->and(KnowledgeGap::first()->status)->toBe('needs_review');
});

it('emits an error event and marks the message failed when the llm is unavailable', function () {
    seedKnowledge(['Кожен працівник має 24 дні відпустки.']);
    app(FakeLlmClient::class)->throws = new LlmException(LlmException::UNAVAILABLE, 'down');

    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'дні відпустки працівник']);

    $events = sseEvents($response->streamedContent());
    expect(array_map(fn ($e) => $e['event'], $events))->toBe(['message', 'error'])
        ->and($events[1]['data']['code'])->toBe('llm_unavailable');
    expect(Message::where('role', 'assistant')->value('status'))->toBe(MessageStatus::Failed);
});

it('rewrites the question with history on the second turn', function () {
    seedKnowledge(['Нові працівники отримують відпустку після 6 місяців.']);
    $this->conversation->messages()->create(['role' => 'user', 'content' => 'Скільки днів відпустки?', 'status' => 'completed']);
    $this->conversation->messages()->create(['role' => 'assistant', 'content' => '24 дні [1].', 'status' => 'completed']);
    $fake = app(FakeLlmClient::class);
    $fake->nextRewrite = 'Коли нові працівники отримують відпустку?';

    $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'а для нових?'])->streamedContent();

    expect($fake->calls)->toHaveKey('rewriteQuestion')
        ->and(Message::where('role', 'assistant')->latest('id')->value('rewritten_question'))->toBe('Коли нові працівники отримують відпустку?')
        ->and($fake->calls['streamAnswer'][0]['user'])->toContain('Коли нові працівники отримують відпустку?');
});

it('validates content length and does not create messages or call the llm', function () {
    $this->actingAs($this->user)->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => ''])->assertStatus(422);
    $this->actingAs($this->user)->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => str_repeat('a', 2001)])->assertStatus(422);

    expect(Message::count())->toBe(0)->and(app(FakeLlmClient::class)->calls)->toBe([]);
});

it('forbids posting into another user conversation', function () {
    $other = Conversation::factory()->create();
    $this->actingAs($this->user)->postJson("/api/v1/conversations/{$other->id}/messages", ['content' => 'hi'])->assertForbidden();
});

it('keeps the streamed answer completed when the grounding check fails', function () {
    seedKnowledge(['Кожен працівник має 24 дні відпустки.']);
    $fake = app(FakeLlmClient::class);
    $fake->nextAnswer = 'Відпустка 24 дні [1].';
    $fake->throws = new LlmException(LlmException::UNAVAILABLE, 'down');
    $fake->throwsOn = 'checkGrounding';

    $response = $this->actingAs($this->user)->post("/api/v1/conversations/{$this->conversation->id}/messages", ['content' => 'дні відпустки працівник']);

    $events = sseEvents($response->streamedContent());
    expect(end($events)['event'])->toBe('done');
    $assistant = Message::where('role', 'assistant')->first();
    expect($assistant->status)->toBe(MessageStatus::Completed)
        ->and($assistant->content)->toBe('Відпустка 24 дні [1].')
        ->and($assistant->grounded)->toBeFalse()
        ->and(KnowledgeGap::first()->status)->toBe('needs_review');
});
