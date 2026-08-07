<?php

use App\Models\User;

it('registers a new user and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'alice@example.com')
        ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token']);

    $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonStructure(['data' => ['id', 'email'], 'token']);
});

it('rejects login with invalid credentials', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable();
});

it('returns the authenticated user on /me', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/me');

    $response->assertOk()->assertJsonPath('data.id', $user->id);
});

it('rejects /me without authentication', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('logs out and revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response->assertNoContent();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});
