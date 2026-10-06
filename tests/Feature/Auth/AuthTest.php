<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;

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

it('renders the forbidden envelope for authorization failures', function () {
    Route::middleware('auth:sanctum')->get('/api/v1/_forbidden', fn () => throw new AuthorizationException);

    $this->actingAs(User::factory()->create())
        ->withHeader('Referer', 'http://localhost')
        ->getJson('/api/v1/_forbidden')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'forbidden');
});
