<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_profile_fields(): void
    {
        $user = User::factory()->create(['name' => 'Minh Anh']);
        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'phone', 'birthday', 'gender', 'address', 'facebook_url', 'avatar_url', 'password_changed_at', 'student_code', 'active_sessions_count']);
    }

    public function test_update_ignores_email_and_role(): void
    {
        $user = User::factory()->create(['email' => 'keep@example.com']);

        $this->actingAs($user)->putJson('/api/v1/me', [
            'name' => 'Tên Mới',
            'email' => 'hacker@example.com',
            'role' => 'admin',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('Tên Mới', $user->name);
        $this->assertSame('keep@example.com', $user->email);
        $this->assertSame('student', $user->role->value);
    }

    public function test_update_validates_phone_and_birthday(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/v1/me', ['name' => 'A', 'phone' => '123abc'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');

        $this->actingAs($user)->putJson('/api/v1/me', ['name' => 'A', 'birthday' => now()->addDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('birthday');
    }

    public function test_facebook_url_gets_https_prefixed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->putJson('/api/v1/me', ['name' => 'A', 'facebook_url' => 'facebook.com/minhanh'])
            ->assertOk();
        $this->assertSame('https://facebook.com/minhanh', $user->fresh()->facebook_url);
    }

    public function test_avatar_over_2mb_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg'); // ~3MB

        $this->actingAs($user)->post('/api/v1/me/avatar', ['avatar' => $file])
            ->assertStatus(422)->assertJsonValidationErrors('avatar');
    }

    public function test_avatar_upload_and_delete(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $url = $this->actingAs($user)->post('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('a.png', 500, 500)])
            ->assertOk()->json('avatar_url');
        $this->assertNotNull($url);
        $this->assertNotNull($user->fresh()->avatar_url);

        $this->actingAs($user)->deleteJson('/api/v1/me/avatar')->assertOk()->assertJsonPath('avatar_url', null);
        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_password_wrong_current_returns_422_on_field(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass1')]);
        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => 'wrong', 'password' => 'NewPass1', 'password_confirmation' => 'NewPass1',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');
    }

    public function test_password_same_as_old_returns_422(): void
    {
        $user = User::factory()->create(['password' => Hash::make('SamePass1')]);
        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => 'SamePass1', 'password' => 'SamePass1', 'password_confirmation' => 'SamePass1',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_password_change_keeps_session_with_new_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass1')]);
        $plain = $user->createToken('student', ['student'])->plainTextToken;

        $res = $this->withToken($plain)->putJson('/api/v1/me/password', [
            'current_password' => 'OldPass1', 'password' => 'NewPass1', 'password_confirmation' => 'NewPass1',
        ])->assertOk();

        $newToken = $res->json('token');
        $this->assertNotNull($newToken);
        // Token mới dùng được ngay → không mất phiên (không bắt đăng nhập lại).
        $this->withToken($newToken)->getJson('/api/v1/me')->assertOk();
        // Sau đổi mật khẩu chỉ còn đúng 1 token (token mới); các token cũ đã thu hồi.
        $this->assertSame(1, $user->fresh()->tokens()->count());
    }

    public function test_logout_others_keeps_only_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('student', ['student'])->plainTextToken;
        $user->createToken('student', ['student']);
        $user->createToken('student', ['student']);
        $this->assertSame(3, $user->tokens()->count());

        $this->withToken($current)->postJson('/api/v1/me/logout-others')
            ->assertOk()->assertJsonPath('revoked_count', 2);
        $this->assertSame(1, $user->fresh()->tokens()->count());
    }

    public function test_password_requires_letter_and_number(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPass1')]);
        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => 'OldPass1', 'password' => 'onlyletters', 'password_confirmation' => 'onlyletters',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }
}
