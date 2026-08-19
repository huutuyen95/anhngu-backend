<?php

namespace Tests\Feature;

use App\Models\Mission;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Nhiệm vụ tự đặt của học viên: thêm từ Thư viện (hạn 7 ngày) → làm xong thì
 * chuyển sang tab Hoàn thành. Bài cô giao KHÔNG hiện ở màn này.
 */
class StudentMissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTest(string $title = 'Đề luyện'): Test
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => $title,
            'slug' => 'de-'.uniqid(),
            'skill' => 'reading',
            'duration_minutes' => 30,
            'total_score' => 10,
            'is_published' => true,
        ]);

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Chọn đáp án đúng']);
        $q = $section->questions()->create(['order' => 1, 'type' => 'multiple_choice', 'content' => 'Q1', 'score' => 1]);
        $q->options()->createMany([
            ['label' => 'A', 'content' => 'Correct', 'is_correct' => true],
            ['label' => 'B', 'content' => 'Wrong', 'is_correct' => false],
        ]);

        return $test->fresh(['parts.sections.questions.options']);
    }

    public function test_student_adds_a_test_and_gets_a_seven_day_target(): void
    {
        Carbon::setTestNow('2026-08-14 09:00:00');
        $student = User::factory()->create();
        $test = $this->makeTest('Unit 5');

        $res = $this->actingAs($student)
            ->postJson('/api/v1/me/missions', ['type' => 'test', 'id' => $test->id])
            ->assertCreated();

        $res->assertJsonPath('mission.status', 'todo');
        $res->assertJsonPath('mission.source', 'self');
        $res->assertJsonPath('mission.due_date', '2026-08-21');
        $res->assertJsonPath('mission.content.type', 'test');
        $res->assertJsonPath('mission.content.title', 'Unit 5');

        Carbon::setTestNow();
    }

    public function test_adding_the_same_test_twice_does_not_duplicate(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest();

        $this->actingAs($student)->postJson('/api/v1/me/missions', ['type' => 'test', 'id' => $test->id])->assertCreated();
        $this->actingAs($student)->postJson('/api/v1/me/missions', ['type' => 'test', 'id' => $test->id])->assertCreated();

        $this->assertSame(1, Mission::where('user_id', $student->id)->count());
    }

    public function test_upcoming_tab_lists_only_unfinished_self_missions(): void
    {
        $student = User::factory()->create();
        $mine = $this->makeTest('Của em');
        $assigned = $this->makeTest('Cô giao');

        $this->actingAs($student)->postJson('/api/v1/me/missions', ['type' => 'test', 'id' => $mine->id]);

        // Bài cô giao — phải KHÔNG xuất hiện ở màn Nhiệm vụ.
        Mission::create([
            'user_id' => $student->id,
            'missionable_type' => $assigned->getMorphClass(),
            'missionable_id' => $assigned->id,
            'source' => 'suggested',
            'status' => 'todo',
        ]);

        $res = $this->actingAs($student)->getJson('/api/v1/me/missions?tab=upcoming')->assertOk();

        $res->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.content.title', 'Của em');
        $res->assertJsonPath('target_days', 7);
    }

    public function test_submitting_the_test_moves_the_mission_to_the_done_tab(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest();

        $this->actingAs($student)->postJson('/api/v1/me/missions', ['type' => 'test', 'id' => $test->id]);

        // Em vào làm bài từ Thư viện (không kèm mission_id) rồi nộp.
        $attemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")->assertOk()->json('attempt_id');
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        $this->actingAs($student)->getJson('/api/v1/me/missions?tab=upcoming')
            ->assertOk()->assertJsonCount(0, 'data');

        $done = $this->actingAs($student)->getJson('/api/v1/me/missions?tab=done')->assertOk();
        $done->assertJsonCount(1, 'data');
        $done->assertJsonPath('data.0.status', 'done');
    }

    public function test_self_mission_attempt_is_not_counted_as_class_assignment(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest();

        $this->actingAs($student)->postJson('/api/v1/me/missions', ['type' => 'test', 'id' => $test->id]);
        $attemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")->assertOk()->json('attempt_id');

        $attempt = TestAttempt::findOrFail($attemptId);
        $this->assertNull($attempt->mission_id, 'Lượt tự luyện không được gắn mission_id');
        $this->assertSame('library', $attempt->source);
    }

    public function test_student_can_remove_a_mission_but_not_someone_elses(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $test = $this->makeTest();

        $missionId = $this->actingAs($student)
            ->postJson('/api/v1/me/missions', ['type' => 'test', 'id' => $test->id])
            ->json('mission.id');

        $this->actingAs($other)->deleteJson("/api/v1/me/missions/{$missionId}")->assertForbidden();
        $this->actingAs($student)->deleteJson("/api/v1/me/missions/{$missionId}")->assertOk();

        $this->assertNull(Mission::find($missionId));
    }

    public function test_test_detail_reports_whether_it_is_already_a_mission(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest();

        $this->actingAs($student)->getJson("/api/v1/tests/{$test->id}")
            ->assertOk()->assertJsonPath('mission', null);

        $this->actingAs($student)->postJson('/api/v1/me/missions', ['type' => 'test', 'id' => $test->id]);

        $this->actingAs($student)->getJson("/api/v1/tests/{$test->id}")
            ->assertOk()->assertJsonPath('mission.status', 'todo');
    }

    public function test_detail_exposes_the_previous_attempt_so_student_can_review_it(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest();

        $this->actingAs($student)->getJson("/api/v1/tests/{$test->id}")
            ->assertOk()->assertJsonPath('attempt', null);

        $attemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")->assertOk()->json('attempt_id');
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        $res = $this->actingAs($student)->getJson("/api/v1/tests/{$test->id}")->assertOk();
        $res->assertJsonPath('attempt.id', $attemptId);
        $res->assertJsonPath('attempt.bucket', 'done');
        $this->assertNotNull($res->json('attempt.best_score'));
    }

    public function test_unsupported_content_type_is_rejected(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->postJson('/api/v1/me/missions', ['type' => 'banh-mi', 'id' => 1])
            ->assertStatus(422);
    }
}
