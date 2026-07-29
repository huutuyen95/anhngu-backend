<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'avatar_url']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_blocked_for_inactive_account(): void
    {
        User::factory()->create([
            'email' => 'locked@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'locked@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)->assertJson(['code' => 'account_locked']);
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        User::factory()->create([
            'email' => 'target@example.com',
            'password' => Hash::make('password'),
        ]);

        RateLimiter::clear('target@example.com|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'target@example.com',
                'password' => 'wrong',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'target@example.com',
            'password' => 'wrong',
        ])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_returns_current_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJson(['id' => $user->id, 'email' => $user->email]);
    }

    public function test_forgot_password_always_returns_ok(): void
    {
        // Email không tồn tại vẫn trả 200 để không lộ email nào có trong hệ thống.
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ])->assertOk();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }
}
