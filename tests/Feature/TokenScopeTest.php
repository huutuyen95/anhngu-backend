<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_login_issues_student_scoped_token(): void
    {
        $student = User::factory()->create([
            'email' => 'hs@example.com',
            'role' => \App\Enums\UserRole::Student,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'hs@example.com',
            'password' => 'password',
        ])->assertOk();

        $token = $student->tokens()->firstOrFail();
        $this->assertSame('student', $token->name);
        $this->assertSame(['student'], $token->abilities);
    }

    public function test_teacher_login_issues_teacher_scoped_token(): void
    {
        $teacher = User::factory()->teacher()->create([
            'email' => 'gv@example.com',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'gv@example.com',
            'password' => 'password',
        ])->assertOk();

        $token = $teacher->tokens()->firstOrFail();
        $this->assertSame('teacher', $token->name);
        $this->assertSame(['teacher', 'student'], $token->abilities);
    }

    public function test_student_token_cannot_reach_teacher_area(): void
    {
        $student = User::factory()->create();
        $plain = $student->issueRoleToken()->plainTextToken;

        // Token thật (Bearer) → EnsureTokenScope thực thi, thiếu ability 'teacher' → 403.
        $this->withToken($plain)
            ->getJson('/api/v1/dashboard')
            ->assertForbidden();
    }

    public function test_teacher_token_reaches_teacher_area(): void
    {
        $teacher = User::factory()->teacher()->create();
        $plain = $teacher->issueRoleToken()->plainTextToken;

        $this->withToken($plain)
            ->getJson('/api/v1/dashboard')
            ->assertOk();
    }

    public function test_student_token_still_reaches_student_area(): void
    {
        $student = User::factory()->create();
        $plain = $student->issueRoleToken()->plainTextToken;

        $this->withToken($plain)
            ->getJson('/api/v1/tests')
            ->assertOk();
    }
}
