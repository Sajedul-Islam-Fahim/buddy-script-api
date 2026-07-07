<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Register ──────────────────────────────────────────────────
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name'            => 'Jane',
            'last_name'             => 'Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['user', 'token']);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_register_requires_all_fields(): void
    {
        $this->postJson('/api/register', [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/register', [
            'first_name'            => 'Jane',
            'last_name'             => 'Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['email']);
    }

    // ── Login ─────────────────────────────────────────────────────
    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret')]);

        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'secret',
        ])->assertOk()
          ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);

        $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ])->assertStatus(422);
    }

    // ── Logout ────────────────────────────────────────────────────
    public function test_user_can_logout(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
             ->postJson('/api/logout')
             ->assertOk();

        // Token should now be revoked
        $this->withToken($token)
             ->getJson('/api/me')
             ->assertUnauthorized();
    }

    // ── Protected route ───────────────────────────────────────────
    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
