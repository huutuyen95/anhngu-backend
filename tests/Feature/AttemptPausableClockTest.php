<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Đồng hồ làm bài chỉ chạy khi học viên đang ở trong màn làm bài: rời ra thì dừng,
 * quay lại thì chạy tiếp từ đúng chỗ đã dừng.
 */
class AttemptPausableClockTest extends TestCase
{
    use RefreshDatabase;

    private function makeTest(int $duration = 30): Test
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Clock Test',
            'slug' => 'clock-test',
            'skill' => 'reading',
            'duration_minutes' => $duration,
            'total_score' => 10,
            'is_published' => true,
        ]);

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Chọn đáp án đúng']);
        $section->questions()->create(['order' => 1, 'type' => 'multiple_choice', 'content' => 'Q1', 'score' => 1]);

        return $test->fresh(['parts.sections.questions']);
    }

    private function startAttempt(User $student, Test $test): int
    {
        return $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->assertOk()
            ->json('attempt_id');
    }

    public function test_clock_stops_while_student_is_away_and_resumes_where_it_left_off(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest(30);

        Carbon::setTestNow('2026-08-14 10:00:00');
        $attemptId = $this->startAttempt($student, $test);

        // Làm bài 5 phút rồi rời màn hình.
        Carbon::setTestNow('2026-08-14 10:05:00');
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/pause")
            ->assertOk()
            ->assertJson(['clock_running' => false, 'remaining_seconds' => 25 * 60, 'deadline' => null]);

        // Đi vắng 2 tiếng — thời gian ở ngoài KHÔNG bị trừ.
        Carbon::setTestNow('2026-08-14 12:05:00');
        $this->actingAs($student)->getJson("/api/v1/attempts/{$attemptId}")
            ->assertOk()
            ->assertJson(['clock_running' => false, 'remaining_seconds' => 25 * 60]);

        // Quay lại làm tiếp: vẫn còn đúng 25 phút, hạn nộp tính từ lúc này.
        $resume = $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/resume")->assertOk();
        $resume->assertJson(['clock_running' => true, 'remaining_seconds' => 25 * 60]);
        $this->assertSame(
            '2026-08-14 12:30:00',
            Carbon::parse($resume->json('deadline'))->format('Y-m-d H:i:s'),
        );

        // Làm thêm 10 phút nữa thì còn 15.
        Carbon::setTestNow('2026-08-14 12:15:00');
        $this->actingAs($student)->getJson("/api/v1/attempts/{$attemptId}")
            ->assertOk()
            ->assertJson(['clock_running' => true, 'remaining_seconds' => 15 * 60]);

        Carbon::setTestNow();
    }

    public function test_pause_is_idempotent_and_does_not_keep_deducting(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest(30);

        Carbon::setTestNow('2026-08-14 10:00:00');
        $attemptId = $this->startAttempt($student, $test);

        Carbon::setTestNow('2026-08-14 10:10:00');
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/pause")->assertOk();

        Carbon::setTestNow('2026-08-14 10:40:00');
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/pause")
            ->assertOk()
            ->assertJson(['remaining_seconds' => 20 * 60]);

        Carbon::setTestNow();
    }

    public function test_untimed_test_has_no_clock_to_pause(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest(0);
        $attemptId = $this->startAttempt($student, $test);

        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/pause")
            ->assertOk()
            ->assertJson(['deadline' => null, 'remaining_seconds' => null]);

        $this->assertNull(TestAttempt::find($attemptId)->remaining_seconds);
    }

    public function test_clock_endpoints_reject_someone_elses_attempt(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $test = $this->makeTest(30);
        $attemptId = $this->startAttempt($owner, $test);

        $this->actingAs($other)->postJson("/api/v1/attempts/{$attemptId}/pause")->assertForbidden();
        $this->actingAs($other)->postJson("/api/v1/attempts/{$attemptId}/resume")->assertForbidden();
    }

    public function test_submitted_attempt_clock_is_not_touched(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest(30);

        Carbon::setTestNow('2026-08-14 10:00:00');
        $attemptId = $this->startAttempt($student, $test);
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        $before = TestAttempt::find($attemptId)->only(['remaining_seconds', 'resumed_at']);
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/pause")->assertOk();
        $this->assertEquals($before, TestAttempt::find($attemptId)->only(['remaining_seconds', 'resumed_at']));

        Carbon::setTestNow();
    }
}
