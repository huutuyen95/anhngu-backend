<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WritingGradingTest extends TestCase
{
    use RefreshDatabase;

    private function makeWritingTest(User $teacher): Test
    {
        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Writing Sample',
            'slug' => 'writing-sample',
            'skill' => 'writing',
            'duration_minutes' => 30,
            'total_score' => 10,
            'word_limit' => 150,
            'is_published' => true,
        ]);

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Viết bài luận']);
        $section->questions()->create(['order' => 1, 'type' => 'writing', 'content' => 'Describe your day.', 'score' => 10]);

        return $test->fresh(['parts.sections.questions']);
    }

    public function test_submitting_a_writing_test_sets_status_pending_review(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeWritingTest($teacher);
        $question = $test->parts->first()->sections->first()->questions->first();

        $attemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');

        $this->actingAs($student)->putJson("/api/v1/attempts/{$attemptId}/answers", [
            'answers' => [
                ['question_id' => $question->id, 'answer_text' => 'It was a normal day.'],
            ],
        ])->assertOk();

        $submit = $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit");

        $submit->assertOk()->assertJson(['status' => 'pending_review']);

        $attempt = TestAttempt::find($attemptId);
        $this->assertSame('pending_review', $attempt->status);
        $this->assertNull($attempt->answers()->first()->is_correct);
    }

    public function test_teacher_can_grade_a_pending_review_attempt(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeWritingTest($teacher);
        $question = $test->parts->first()->sections->first()->questions->first();

        $attemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');
        $this->actingAs($student)->putJson("/api/v1/attempts/{$attemptId}/answers", [
            'answers' => [['question_id' => $question->id, 'answer_text' => 'It was a normal day.']],
        ]);
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        $queue = $this->actingAs($teacher)->getJson('/api/v1/admin/attempts?status=pending_review');
        $queue->assertOk()->assertJsonCount(1, 'data');

        $grade = $this->actingAs($teacher)->postJson("/api/v1/admin/attempts/{$attemptId}/grade", [
            'answers' => [
                ['question_id' => $question->id, 'score' => 7.5, 'feedback' => 'Khá tốt.'],
            ],
        ]);

        $grade->assertOk()->assertJson([
            'attempt' => ['status' => 'graded', 'total_score' => 7.5],
        ]);

        $attempt = TestAttempt::find($attemptId);
        $this->assertSame('graded', $attempt->status);
        $this->assertEquals(7.5, $attempt->total_score);

        $answer = $attempt->answers()->first();
        $this->assertEquals(7.5, $answer->score);
        $this->assertSame('Khá tốt.', $answer->feedback);
        $this->assertSame($teacher->id, $answer->graded_by);
        $this->assertNotNull($answer->graded_at);
    }

    /** Nộp bài writing rồi trả về entry của đề đó trong `GET /api/v1/tests`. */
    private function submitAndListEntry(User $student, Test $test): array
    {
        $question = $test->parts->first()->sections->first()->questions->first();

        $attemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');
        $this->actingAs($student)->putJson("/api/v1/attempts/{$attemptId}/answers", [
            'answers' => [['question_id' => $question->id, 'answer_text' => 'It was a normal day.']],
        ]);
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        return [$attemptId, $question];
    }

    private function listEntry(User $student, Test $test): array
    {
        $response = $this->actingAs($student)->getJson('/api/v1/tests');
        $response->assertOk();

        return collect($response->json('data'))->firstWhere('id', $test->id);
    }

    public function test_list_shows_pending_review_instead_of_treating_it_as_not_started(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeWritingTest($teacher);

        $this->submitAndListEntry($student, $test);

        $entry = $this->listEntry($student, $test);

        $this->assertNotNull($entry['attempt'], 'Đề chờ chấm không được hiện "chưa làm".');
        $this->assertSame('pending_review', $entry['attempt']['status']);
        // Điểm tạm của phần tự chấm chưa phải điểm thật → không trả ra làm best_score.
        $this->assertNull($entry['attempt']['best_score']);
    }

    public function test_list_shows_graded_status_and_score_after_teacher_grades(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeWritingTest($teacher);

        [$attemptId, $question] = $this->submitAndListEntry($student, $test);

        $this->actingAs($teacher)->postJson("/api/v1/admin/attempts/{$attemptId}/grade", [
            'answers' => [['question_id' => $question->id, 'score' => 7.5, 'feedback' => 'Khá tốt.']],
        ])->assertOk();

        $entry = $this->listEntry($student, $test);

        $this->assertNotNull($entry['attempt'], 'Đề đã chấm xong không được hiện "chưa làm".');
        $this->assertSame('graded', $entry['attempt']['status']);
        $this->assertEquals(7.5, $entry['attempt']['best_score']);
    }

    public function test_submitting_logs_activity_with_test_id(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeWritingTest($teacher);

        $this->submitAndListEntry($student, $test);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $student->id,
            'test_id' => $test->id,
            'type' => 'test_attempt',
        ]);
    }

    public function test_student_cannot_access_admin_attempt_endpoints(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeWritingTest($teacher);

        $this->actingAs($student)->getJson('/api/v1/admin/attempts')->assertForbidden();
        $this->actingAs($student)->postJson('/api/v1/admin/tests', ['title' => 'x', 'skill' => 'writing'])->assertForbidden();
    }
}
