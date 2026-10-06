<?php

use App\Chat\Models\Conversation;
use App\Models\User;

it('creates, lists and reads conversations with messages and citations', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson('/api/v1/conversations')->assertCreated()->assertJsonPath('data.title', null);
    $conv = Conversation::first();
    $msg = $conv->messages()->create(['role' => 'assistant', 'content' => 'x [1]', 'status' => 'completed']);
    $msg->citations()->create(['marker' => 1, 'chunk_id' => null, 'quote' => 'цитата']);

    $this->actingAs($user)->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($user)->getJson("/api/v1/conversations/{$conv->id}/messages")->assertOk()
        ->assertJsonPath('data.0.citations.0.quote', 'цитата')
        ->assertJsonPath('data.0.citations.0.chunk_id', null);
});

it('hides other users conversations', function () {
    $user = User::factory()->create();
    $foreign = Conversation::factory()->create();
    $this->actingAs($user)->getJson("/api/v1/conversations/{$foreign->id}/messages")->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/conversations')->assertJsonCount(0, 'data');
});
