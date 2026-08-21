<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Mission;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentReportTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Classroom $classA;

    private Classroom $classB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->create();
        $this->classA = $this->makeClass('Lớp A', 'r-a');
        $this->classB = $this->makeClass('Lớp B', 'r-b');
        $this->classA->students()->attach($this->student->id, ['status' => 'studying']);
        $this->classB->students()->attach($this->student->id, ['status' => 'studying']);
    }

    private function makeClass(string $name, string $slug): Classroom
    {
        return Classroom::create(['teacher_id' => $this->teacher->id, 'name' => $name, 'slug' => $slug, 'is_active' => true]);
    }

    private function makeTest(): Test
    {
        return Test::create(['created_by' => $this->teacher->id, 'title' => 'Đề '.uniqid(), 'slug' => 'de-'.uniqid(),
            'skill' => 'reading', 'duration_minutes' => 30, 'total_score' => 10, 'is_published' => true]);
    }

    private function scoredAttempt(Classroom $class, float $score, $startedAt): TestAttempt
    {
        $test = $this->makeTest();
        $session = $class->sessions()->create(['title' => 'B', 'order' => 1, 'is_visible' => true]);
        $mission = Mission::create(['user_id' => $this->student->id, 'assigned_by' => $this->teacher->id,
            'classroom_id' => $class->id, 'class_session_id' => $session->id, 'missionable_type' => $test->getMorphClass(),
            'missionable_id' => $test->id, 'source' => 'suggested', 'status' => 'done', 'completed_at' => $startedAt]);

        return TestAttempt::create(['user_id' => $this->student->id, 'test_id' => $test->id, 'classroom_id' => $class->id,
            'mission_id' => $mission->id, 'source' => 'assignment', 'status' => 'graded', 'total_score' => $score,
            'question_count' => 10, 'started_at' => $startedAt, 'submitted_at' => $startedAt->copy()->addMinutes(20)]);
    }

    public function test_overview_sums_all_classes(): void
    {
        $this->scoredAttempt($this->classA, 8, now()); // 80%
        $this->scoredAttempt($this->classB, 6, now()); // 60%

        $res = $this->actingAs($this->student)->getJson('/api/v1/me/report?scope=overview&period=30d')->assertOk();
        $this->assertSame(2, $res->json('stats.attempts'));
        $this->assertSame(2, $res->json('stats.completed'));
        $this->assertSame(70, $res->json('stats.avg_score')); // (80+60)/2
        $this->assertCount(2, $res->json('class_progress'));
    }

    public function test_class_scope_filters_to_one_class(): void
    {
        $this->scoredAttempt($this->classA, 9, now());
        $this->scoredAttempt($this->classB, 5, now());

        $res = $this->actingAs($this->student)
            ->getJson("/api/v1/me/report?scope=class&classroom_id={$this->classA->id}&period=30d")->assertOk();
        $this->assertSame(1, $res->json('stats.attempts'));
        $this->assertSame(90, $res->json('stats.avg_score'));
        $this->assertCount(1, $res->json('class_progress'));
    }

    public function test_period_filters_out_old_attempts(): void
    {
        $this->scoredAttempt($this->classA, 8, now());
        $this->scoredAttempt($this->classA, 4, now()->subDays(40));

        $this->assertSame(1, $this->actingAs($this->student)->getJson('/api/v1/me/report?period=7d')->json('stats.attempts'));
        $this->assertSame(2, $this->actingAs($this->student)->getJson('/api/v1/me/report?period=90d')->json('stats.attempts'));
    }

    public function test_pending_review_not_counted_in_average(): void
    {
        $this->scoredAttempt($this->classA, 8, now());
        // Lượt chờ chấm có điểm tạm nhưng không được tính vào TB.
        $test = $this->makeTest();
        $session = $this->classA->sessions()->create(['title' => 'B', 'order' => 2, 'is_visible' => true]);
        $m = Mission::create(['user_id' => $this->student->id, 'assigned_by' => $this->teacher->id,
            'classroom_id' => $this->classA->id, 'class_session_id' => $session->id, 'missionable_type' => $test->getMorphClass(),
            'missionable_id' => $test->id, 'source' => 'suggested', 'status' => 'todo']);
        TestAttempt::create(['user_id' => $this->student->id, 'test_id' => $test->id, 'classroom_id' => $this->classA->id,
            'mission_id' => $m->id, 'source' => 'assignment', 'status' => 'pending_review', 'total_score' => 2,
            'question_count' => 10, 'started_at' => now(), 'submitted_at' => now()]);

        $this->assertSame(80, $this->actingAs($this->student)->getJson('/api/v1/me/report?period=30d')->json('stats.avg_score'));
    }

    public function test_non_member_cannot_view_class_report(): void
    {
        $other = $this->makeClass('Lớp khác', 'r-other');
        $this->actingAs($this->student)
            ->getJson("/api/v1/me/report?scope=class&classroom_id={$other->id}")->assertForbidden();
    }
}
