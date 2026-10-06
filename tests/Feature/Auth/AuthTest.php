<?php

use App\Models\User;

it('logs in with valid credentials and returns me', function () {
    $user = User::factory()->hrAdmin()->create(['email' => 'hr@vesna.test', 'password' => 'secret123']);

    $this->withHeader('Referer', 'http://localhost')
        ->postJson('/api/v1/auth/login', ['email' => 'hr@vesna.test', 'password' => 'secret123'])
        ->assertNoContent();

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'hr@vesna.test')
        ->assertJsonPath('data.role', 'hr_admin');
});

it('rejects invalid credentials with error envelope', function () {
    User::factory()->create(['email' => 'u@vesna.test', 'password' => 'secret123']);

    $this->withHeader('Referer', 'http://localhost')
        ->postJson('/api/v1/auth/login', ['email' => 'u@vesna.test', 'password' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

it('returns 401 envelope for guests', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});

it('logs out', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->withHeader('Referer', 'http://localhost')->postJson('/api/v1/auth/logout')->assertNoContent();
});
