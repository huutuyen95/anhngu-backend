<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\SessionAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassDetailC3Test extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Classroom $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->class = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Lớp C3', 'slug' => 'lop-c3', 'is_active' => true,
        ]);
    }

    public function test_attendance_bulk_upsert_is_idempotent(): void
    {
        $student = User::factory()->create();
        $this->class->students()->attach($student->id, ['status' => 'studying']);
        $session = $this->class->sessions()->create(['title' => 'B1', 'order' => 1]);

        $payload = ['items' => [['user_id' => $student->id, 'status' => 'late', 'comment' => 'Đi muộn 5 phút']]];

        $this->actingAs($this->teacher)->putJson("/api/v1/sessions/{$session->id}/attendances/bulk", $payload)->assertOk();
        $this->actingAs($this->teacher)->putJson("/api/v1/sessions/{$session->id}/attendances/bulk", $payload)->assertOk();

        // Gọi 2 lần → chỉ 1 bản ghi (UNIQUE).
        $this->assertEquals(1, SessionAttendance::where('class_session_id', $session->id)->count());
        $this->assertEquals('late', SessionAttendance::first()->status);
    }

    public function test_attendance_index_lists_all_class_students(): void
    {
        $students = User::factory()->count(3)->create();
        $this->class->students()->attach($students->pluck('id')->all(), ['status' => 'studying']);
        $session = $this->class->sessions()->create(['title' => 'B1', 'order' => 1]);

        $this->actingAs($this->teacher)
            ->getJson("/api/v1/sessions/{$session->id}/attendances")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_report_works_with_no_submissions(): void
    {
        $this->actingAs($this->teacher)
            ->getJson("/api/v1/classrooms/{$this->class->id}/report?period=30d")
            ->assertOk()
            ->assertJsonStructure(['stats' => ['active_students', 'attempts'], 'weekly_avg', 'score_buckets', 'by_session', 'by_student', 'pending_count']);
    }

    public function test_quick_create_student_returns_temp_password(): void
    {
        $this->actingAs($this->teacher)
            ->postJson("/api/v1/classrooms/{$this->class->id}/students/quick", [
                'name' => 'HS Nhanh', 'email' => 'nhanh@example.com',
            ])
            ->assertCreated()
            ->assertJsonStructure(['student' => ['id'], 'temp_password']);

        $this->assertEquals(1, $this->class->students()->count());
    }

    public function test_removing_student_from_class_keeps_account(): void
    {
        $student = User::factory()->create();
        $this->class->students()->attach($student->id, ['status' => 'studying']);

        $this->actingAs($this->teacher)
            ->deleteJson("/api/v1/classrooms/{$this->class->id}/students/{$student->id}")
            ->assertOk();

        $this->assertEquals(0, $this->class->students()->count());
        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }

    public function test_student_cannot_access_report(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson("/api/v1/classrooms/{$this->class->id}/report")
            ->assertStatus(403);
    }
}
