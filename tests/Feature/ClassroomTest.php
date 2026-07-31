<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): User
    {
        return User::factory()->teacher()->create();
    }

    private function makeClass(array $attrs = []): Classroom
    {
        return Classroom::create(array_merge([
            'teacher_id' => User::factory()->teacher()->create()->id,
            'name' => 'Lớp '.uniqid(),
            'slug' => 'lop-'.uniqid(),
            'is_active' => true,
        ], $attrs));
    }

    public function test_teacher_lists_classrooms(): void
    {
        $this->makeClass(['name' => 'Lớp 12F']);
        $this->makeClass(['name' => 'Lớp 11A']);

        $this->actingAs($this->teacher())
            ->getJson('/api/v1/classrooms')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['id', 'name', 'status', 'students_count', 'progress_pct', 'avg_score']]]);
    }

    public function test_filter_by_status_upcoming(): void
    {
        $this->makeClass(['name' => 'Sắp mở', 'starts_on' => now()->addWeek()->toDateString()]);
        $this->makeClass(['name' => 'Đang chạy']);

        $this->actingAs($this->teacher())
            ->getJson('/api/v1/classrooms?status=upcoming')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Sắp mở');
    }

    public function test_status_derived_from_dates(): void
    {
        $ended = $this->makeClass([
            'starts_on' => now()->subMonths(2)->toDateString(),
            'ends_on' => now()->subWeek()->toDateString(),
        ]);

        $this->assertEquals('ended', $ended->status());
    }

    public function test_create_classroom(): void
    {
        $this->actingAs($this->teacher())
            ->postJson('/api/v1/classrooms', [
                'name' => 'Lớp mới',
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addMonths(3)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('classroom.name', 'Lớp mới');

        $this->assertDatabaseHas('classrooms', ['name' => 'Lớp mới']);
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $this->actingAs($this->teacher())
            ->postJson('/api/v1/classrooms', [
                'name' => 'Sai ngày',
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->subDay()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_on');
    }

    public function test_deleting_class_with_students_requires_confirm(): void
    {
        $teacher = $this->teacher();
        $class = $this->makeClass();
        $student = User::factory()->create();
        $class->students()->attach($student->id, ['status' => 'studying']);

        // Không confirm → 409
        $this->actingAs($teacher)
            ->deleteJson("/api/v1/classrooms/{$class->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'needs_confirm');

        // Có confirm → xoá lớp, giữ tài khoản HS
        $this->actingAs($teacher)
            ->deleteJson("/api/v1/classrooms/{$class->id}?confirm=1")
            ->assertOk();

        $this->assertDatabaseMissing('classrooms', ['id' => $class->id]);
        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }

    public function test_dashboard_returns_shape(): void
    {
        $this->makeClass();

        $this->actingAs($this->teacher())
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'stats' => ['classes', 'pending_review', 'open_missions', 'avg_score_week'],
                'classes', 'todos', 'activities',
            ]);
    }

    public function test_student_cannot_access_classrooms(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/classrooms')
            ->assertStatus(403);
    }
}
