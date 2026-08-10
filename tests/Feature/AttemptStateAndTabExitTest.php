<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptStateAndTabExitTest extends TestCase
{
    use RefreshDatabase;

    private function makeTest(int $duration = 30): Test
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Sample Test',
            'slug' => 'sample-test',
            'skill' => 'reading',
            'duration_minutes' => $duration,
            'total_score' => 10,
            'is_published' => true,
        ]);

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Chọn đáp án đúng']);
        $mc = $section->questions()->create(['order' => 1, 'type' => 'multiple_choice', 'content' => 'Q1', 'score' => 1]);
        $mc->options()->createMany([
            ['label' => 'A', 'content' => 'Correct', 'is_correct' => true],
            ['label' => 'B', 'content' => 'Wrong', 'is_correct' => false],
        ]);

        return $test->fresh(['parts.sections.questions.options']);
    }

    private function startAttempt(User $student, Test $test): int
    {
        return $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->assertOk()
            ->json('attempt_id');
    }

    public function test_show_returns_deadline_from_server_and_saved_answers(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest(30);
        $attemptId = $this->startAttempt($student, $test);

        $question = $test->parts->first()->sections->first()->questions->first();
        $option = $question->options->firstWhere('is_correct', true);

        $this->actingAs($student)->putJson("/api/v1/attempts/{$attemptId}/answers", [
            'answers' => [
                ['question_id' => $question->id, 'question_option_id' => $option->id],
            ],
        ])->assertOk();

        $res = $this->actingAs($student)->getJson("/api/v1/attempts/{$attemptId}")->assertOk();

        $res->assertJsonPath('status', 'in_progress')
            ->assertJsonPath('tab_exit_count', 0)
            ->assertJsonPath('tab_exit_limit', TestAttempt::TAB_EXIT_LIMIT)
            ->assertJsonPath('answers.0.question_id', $question->id)
            ->assertJsonPath('answers.0.question_option_id', $option->id);

        $this->assertNotNull($res->json('deadline'));
    }

    public function test_show_deadline_is_null_when_test_is_untimed(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest(0);
        $attemptId = $this->startAttempt($student, $test);

        $this->actingAs($student)->getJson("/api/v1/attempts/{$attemptId}")
            ->assertOk()
            ->assertJsonPath('deadline', null);
    }

    public function test_tab_exit_increments_count_without_submitting(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest();
        $attemptId = $this->startAttempt($student, $test);

        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/tab-exit")
            ->assertOk()
            ->assertJsonPath('tab_exit_count', 1)
            ->assertJsonPath('auto_submitted', false);

        $this->assertDatabaseHas('test_attempts', [
            'id' => $attemptId,
            'tab_exit_count' => 1,
            'status' => 'in_progress',
        ]);
    }

    public function test_exceeding_tab_exit_limit_auto_submits(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest();
        $attemptId = $this->startAttempt($student, $test);

        // LIMIT lần đầu chỉ đếm, lần thứ LIMIT+1 mới tự nộp.
        for ($i = 0; $i < TestAttempt::TAB_EXIT_LIMIT; $i++) {
            $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/tab-exit")
                ->assertOk()
                ->assertJsonPath('auto_submitted', false);
        }

        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/tab-exit")
            ->assertOk()
            ->assertJsonPath('auto_submitted', true)
            ->assertJsonPath('reason', 'tab_exit_exceeded');

        $this->assertDatabaseMissing('test_attempts', [
            'id' => $attemptId,
            'status' => 'in_progress',
        ]);
    }

    public function test_tab_exit_after_submit_does_not_resubmit(): void
    {
        $student = User::factory()->create();
        $test = $this->makeTest();
        $attemptId = $this->startAttempt($student, $test);

        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/tab-exit")
            ->assertOk()
            ->assertJsonPath('auto_submitted', true)
            ->assertJsonPath('tab_exit_count', 0);
    }

    public function test_cannot_read_or_report_exit_on_someone_elses_attempt(): void
    {
        $student = User::factory()->create();
        $intruder = User::factory()->create();
        $test = $this->makeTest();
        $attemptId = $this->startAttempt($student, $test);

        $this->actingAs($intruder)->getJson("/api/v1/attempts/{$attemptId}")->assertForbidden();
        $this->actingAs($intruder)->postJson("/api/v1/attempts/{$attemptId}/tab-exit")->assertForbidden();
    }
}
