<?php

namespace Tests\Feature;

use App\Enums\Skill;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentTest extends TestCase
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
            'name' => 'Lớp Test',
            'slug' => 'lop-test',
            'is_active' => true,
        ]);
    }

    private function makeTest(): Test
    {
        return Test::create([
            'created_by' => $this->teacher->id,
            'title' => 'Đề 1',
            'slug' => 'de-1-'.uniqid(),
            'skill' => Skill::Reading,
            'duration_minutes' => 30,
            'total_score' => 10,
            'is_published' => true,
        ]);
    }

    public function test_overview_works_when_class_has_no_sessions(): void
    {
        $this->actingAs($this->teacher)
            ->getJson("/api/v1/classrooms/{$this->class->id}/overview")
            ->assertOk()
            ->assertJsonStructure(['stats' => ['progress_pct', 'active_students', 'total_students'], 'sessions', 'at_risk']);
    }

    public function test_create_and_reorder_sessions(): void
    {
        $s1 = $this->actingAs($this->teacher)
            ->postJson("/api/v1/classrooms/{$this->class->id}/sessions", ['title' => 'Buổi 1'])
            ->assertCreated()->json('session.id');
        $s2 = $this->actingAs($this->teacher)
            ->postJson("/api/v1/classrooms/{$this->class->id}/sessions", ['title' => 'Buổi 2'])
            ->assertCreated()->json('session.id');

        $this->actingAs($this->teacher)
            ->patchJson('/api/v1/sessions/reorder', ['ids' => [$s2, $s1]])
            ->assertOk();

        $this->assertEquals(1, \App\Models\ClassSession::find($s2)->order);
        $this->assertEquals(2, \App\Models\ClassSession::find($s1)->order);
    }

    public function test_assignment_creates_missions_for_each_active_student(): void
    {
        $students = User::factory()->count(3)->create();
        $this->class->students()->attach($students->pluck('id')->all(), ['status' => 'studying']);
        $session = $this->class->sessions()->create(['title' => 'Buổi 1', 'order' => 1]);
        $test = $this->makeTest();

        $this->actingAs($this->teacher)
            ->postJson('/api/v1/assignments', [
                'classroom_id' => $this->class->id,
                'class_session_id' => $session->id,
                'items' => [['type' => 'test', 'id' => $test->id]],
                'schedule' => 'now',
            ])
            ->assertCreated()
            ->assertJsonPath('created', 3)
            ->assertJsonPath('students_targeted', 3);

        $this->assertEquals(3, Mission::where('classroom_id', $this->class->id)->count());
    }

    public function test_assignment_skips_duplicates_and_locked(): void
    {
        $active = User::factory()->count(2)->create();
        $locked = User::factory()->inactive()->create();
        $this->class->students()->attach(
            [...$active->pluck('id')->all(), $locked->id],
            ['status' => 'studying']
        );
        $session = $this->class->sessions()->create(['title' => 'Buổi 1', 'order' => 1]);
        $test = $this->makeTest();

        $payload = [
            'classroom_id' => $this->class->id,
            'class_session_id' => $session->id,
            'items' => [['type' => 'test', 'id' => $test->id]],
            'schedule' => 'now',
        ];

        // Lần 1: 2 active nhận, 1 locked bị loại.
        $this->actingAs($this->teacher)->postJson('/api/v1/assignments', $payload)
            ->assertCreated()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('excluded_locked', 1);

        // Lần 2: cùng đề → tất cả trùng.
        $this->actingAs($this->teacher)->postJson('/api/v1/assignments', $payload)
            ->assertCreated()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('duplicates', 2);
    }

    public function test_scheduled_in_past_is_rejected(): void
    {
        $session = $this->class->sessions()->create(['title' => 'B1', 'order' => 1]);
        $test = $this->makeTest();

        $this->actingAs($this->teacher)
            ->postJson('/api/v1/assignments', [
                'classroom_id' => $this->class->id,
                'class_session_id' => $session->id,
                'items' => [['type' => 'test', 'id' => $test->id]],
                'schedule' => 'at',
                'scheduled_at' => now()->subDay()->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_at');
    }
}
