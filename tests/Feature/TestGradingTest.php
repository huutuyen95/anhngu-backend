<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestGradingTest extends TestCase
{
    use RefreshDatabase;

    private function makeTest(): Test
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Sample Test',
            'slug' => 'sample-test',
            'skill' => 'reading',
            'duration_minutes' => 30,
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

        $select = $section->questions()->create(['order' => 2, 'type' => 'select', 'content' => 'Q2', 'score' => 1]);
        $select->options()->createMany([
            ['content' => 'True', 'is_correct' => true],
            ['content' => 'False', 'is_correct' => false],
        ]);

        $fillBlank = $section->questions()->create(['order' => 3, 'type' => 'fill_blank', 'content' => 'Q3', 'score' => 1]);
        $fillBlank->options()->createMany([
            ['content' => 'Pearl', 'is_correct' => true],
            ['content' => 'Jewel', 'is_correct' => true],
        ]);

        $section->questions()->create(['order' => 4, 'type' => 'upload', 'content' => 'Q4', 'score' => 1]);

        return $test->fresh(['parts.sections.questions.options']);
    }

    public function test_submit_grades_mixed_answers_and_ignores_upload_questions(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest();
        $questions = $test->parts->first()->sections->first()->questions;

        [$mc, $select, $fillBlank, $upload] = [$questions[0], $questions[1], $questions[2], $questions[3]];

        $mcCorrectOption = $mc->options->firstWhere('is_correct', true);
        $selectWrongOption = $select->options->firstWhere('is_correct', false);

        $start = $this->actingAs($student)->postJson("/api/v1/tests/{$test->id}/attempts");
        $start->assertOk();
        $attemptId = $start->json('attempt_id');

        $this->actingAs($student)->putJson("/api/v1/attempts/{$attemptId}/answers", [
            'answers' => [
                ['question_id' => $mc->id, 'question_option_id' => $mcCorrectOption->id],
                ['question_id' => $select->id, 'question_option_id' => $selectWrongOption->id],
                // Chấp nhận nhiều cách viết + không phân biệt hoa/thường + khoảng trắng thừa.
                ['question_id' => $fillBlank->id, 'answer_text' => ' jewel '],
                ['question_id' => $upload->id, 'answer_text' => 'link bài nói đã nộp'],
            ],
        ])->assertOk();

        $submit = $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit");

        $submit->assertOk()->assertJson([
            'correct_count' => 2,
            'question_count' => 4,
        ]);
        // 2 câu đúng / 3 câu tự chấm (bỏ qua upload) * 10 điểm = 6.67
        $this->assertEquals(6.67, $submit->json('total_score'));

        $attempt = TestAttempt::find($attemptId);
        $this->assertSame('submitted', $attempt->status);

        $uploadAnswer = $attempt->answers()->where('question_id', $upload->id)->first();
        $this->assertNull($uploadAnswer->is_correct);
    }

    public function test_taking_test_hides_correct_answers_but_result_reveals_them(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest();

        $examResponse = $this->actingAs($student)->getJson("/api/v1/tests/{$test->id}");
        $examResponse->assertOk();
        $this->assertArrayNotHasKey('is_correct', $examResponse->json('parts.0.sections.0.questions.0.options.0'));
        $this->assertArrayNotHasKey('explanation', $examResponse->json('parts.0.sections.0.questions.0'));

        $start = $this->actingAs($student)->postJson("/api/v1/tests/{$test->id}/attempts");
        $attemptId = $start->json('attempt_id');
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        $result = $this->actingAs($student)->getJson("/api/v1/attempts/{$attemptId}/result");
        $result->assertOk();
        $this->assertArrayHasKey('is_correct', $result->json('test.parts.0.sections.0.questions.0.options.0'));
    }

    public function test_other_users_cannot_access_someone_elses_attempt(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $intruder = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest();

        $attemptId = $this->actingAs($owner)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');

        $this->actingAs($intruder)
            ->getJson("/api/v1/attempts/{$attemptId}/result")
            ->assertForbidden();
    }
}
