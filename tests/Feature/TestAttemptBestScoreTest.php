<?php

namespace Tests\Feature;

use App\Models\AttemptAnswer;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestAttemptBestScoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Đề có 2 câu multiple_choice bằng nhau → điểm khả dĩ: 0, 5, 10 (thang total_score=10).
     */
    private function makeTest(): Test
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Best Score Test',
            'slug' => 'best-score-test',
            'skill' => 'reading',
            'duration_minutes' => 30,
            'total_score' => 10,
            'is_published' => true,
        ]);

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Chọn đáp án đúng']);

        foreach ([1, 2] as $order) {
            $question = $section->questions()->create([
                'order' => $order,
                'type' => 'multiple_choice',
                'content' => "Q{$order}",
                'score' => 1,
            ]);
            $question->options()->createMany([
                ['label' => 'A', 'content' => 'Correct', 'is_correct' => true],
                ['label' => 'B', 'content' => 'Wrong', 'is_correct' => false],
            ]);
        }

        return $test->fresh(['parts.sections.questions.options']);
    }

    private function questions(Test $test)
    {
        return $test->parts->first()->sections->first()->questions;
    }

    private function submitWithScore(User $student, Test $test, int $correctCount): array
    {
        $questions = $this->questions($test);

        $start = $this->actingAs($student)->postJson("/api/v1/tests/{$test->id}/attempts");
        $attemptId = $start->json('attempt_id');

        $answers = $questions->map(function ($question, $index) use ($correctCount) {
            $wanted = $index < $correctCount;
            $option = $question->options->firstWhere('is_correct', $wanted);

            return ['question_id' => $question->id, 'question_option_id' => $option->id];
        })->values()->all();

        $this->actingAs($student)->putJson("/api/v1/attempts/{$attemptId}/answers", ['answers' => $answers])->assertOk();

        $submit = $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit");
        $submit->assertOk();

        return ['attempt_id' => $attemptId, 'response' => $submit];
    }

    public function test_lower_second_attempt_is_discarded_and_best_kept(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest();

        $first = $this->submitWithScore($student, $test, 2); // 10 điểm, best mới (chưa có best cũ)
        $first['response']->assertJson(['total_score' => 10.0, 'is_new_best' => true]);

        $second = $this->submitWithScore($student, $test, 1); // 5 điểm, thấp hơn best
        $second['response']->assertJson(['total_score' => 5.0, 'is_new_best' => false]);
        // Payload nộp lần 2 vẫn trả đầy đủ answers/test dù attempt đó sẽ bị xoá.
        $this->assertNotEmpty($second['response']->json('answers'));
        $this->assertNotEmpty($second['response']->json('test.parts'));

        $rows = TestAttempt::where('user_id', $student->id)->where('test_id', $test->id)->get();
        $this->assertCount(1, $rows);
        $kept = $rows->first();
        $this->assertSame($first['attempt_id'], $kept->id);
        $this->assertEquals(10.0, (float) $kept->total_score);
        $this->assertSame(2, $kept->attempt_count);
        $this->assertNotNull($kept->last_attempted_at);

        $this->assertNull(TestAttempt::find($second['attempt_id']));
        $this->assertSame(2, AttemptAnswer::where('test_attempt_id', $kept->id)->count());
        $this->assertSame(0, AttemptAnswer::where('test_attempt_id', $second['attempt_id'])->count());
    }

    public function test_higher_second_attempt_replaces_previous_best(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest();

        $first = $this->submitWithScore($student, $test, 1); // 5 điểm, best mới
        $first['response']->assertJson(['total_score' => 5.0, 'is_new_best' => true]);

        $second = $this->submitWithScore($student, $test, 2); // 10 điểm, cao hơn best cũ
        $second['response']->assertJson(['total_score' => 10.0, 'is_new_best' => true]);

        $rows = TestAttempt::where('user_id', $student->id)->where('test_id', $test->id)->get();
        $this->assertCount(1, $rows);
        $kept = $rows->first();
        $this->assertSame($second['attempt_id'], $kept->id);
        $this->assertEquals(10.0, (float) $kept->total_score);
        $this->assertSame(2, $kept->attempt_count);

        $this->assertNull(TestAttempt::find($first['attempt_id']));
    }

    public function test_starting_new_attempt_deletes_stale_in_progress_attempt(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest();

        $firstAttemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');

        $question = $this->questions($test)->first();
        $this->actingAs($student)->putJson("/api/v1/attempts/{$firstAttemptId}/answers", [
            'answers' => [['question_id' => $question->id, 'question_option_id' => $question->options->first()->id]],
        ])->assertOk();

        $secondAttemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');

        $this->assertNotEquals($firstAttemptId, $secondAttemptId);
        $this->assertNull(TestAttempt::find($firstAttemptId));
        $this->assertSame(0, AttemptAnswer::where('test_attempt_id', $firstAttemptId)->count());
        $this->assertSame(1, TestAttempt::where('user_id', $student->id)->where('test_id', $test->id)->count());
    }

    public function test_get_tests_reports_best_score_and_attempt_count(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest();

        $before = $this->actingAs($student)->getJson('/api/v1/tests');
        $before->assertOk()->assertJsonFragment(['attempt' => null]);

        $this->submitWithScore($student, $test, 2); // 10 điểm
        $this->submitWithScore($student, $test, 1); // 5 điểm, bị loại

        $after = $this->actingAs($student)->getJson('/api/v1/tests');
        $after->assertOk();

        $entry = collect($after->json())->firstWhere('id', $test->id);
        $this->assertEquals(10.0, $entry['attempt']['best_score']);
        $this->assertSame(2, $entry['attempt']['attempt_count']);
        $this->assertSame('submitted', $entry['attempt']['status']);
        $this->assertNotNull($entry['attempt']['last_attempted_at']);
    }
}
