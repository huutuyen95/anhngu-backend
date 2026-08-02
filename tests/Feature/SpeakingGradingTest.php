<?php

namespace Tests\Feature;

use App\Models\AttemptAnswer;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SpeakingGradingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSpeakingTest(User $teacher): Test
    {
        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Speaking Sample',
            'slug' => 'speaking-sample',
            'skill' => 'speaking',
            'duration_minutes' => 15,
            'total_score' => 10,
            'is_published' => true,
        ]);

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Ghi âm trả lời']);
        $section->questions()->create([
            'order' => 1,
            'type' => 'speaking',
            'content' => 'Describe your favorite hobby.',
            'images' => ['https://example.com/pic1.jpg', 'https://example.com/pic2.jpg'],
            'record_limit_seconds' => 60,
            'score' => 10,
        ]);

        return $test->fresh(['parts.sections.questions']);
    }

    public function test_student_can_upload_and_replace_audio_answer(): void
    {
        Storage::fake('public');

        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeSpeakingTest($teacher);
        $question = $test->parts->first()->sections->first()->questions->first();

        $attemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');

        $audioUrl = "/api/v1/attempts/{$attemptId}/answers/{$question->id}/audio";

        $first = UploadedFile::fake()->create('answer1.mp3', 500, 'audio/mpeg');
        $upload = $this->actingAs($student)->post($audioUrl, ['file' => $first]);
        $upload->assertOk();
        $firstUrl = $upload->json('url');
        $this->assertNotNull($firstUrl);

        $answer = AttemptAnswer::where('test_attempt_id', $attemptId)->where('question_id', $question->id)->first();
        $this->assertSame($firstUrl, $answer->answer_file_url);
        Storage::disk('public')->assertExists('answers/audio/'.basename($firstUrl));

        // Nộp lại (ghi âm lại) — file cũ phải bị xoá khỏi disk.
        $second = UploadedFile::fake()->create('answer2.mp3', 500, 'audio/mpeg');
        $reupload = $this->actingAs($student)->post($audioUrl, ['file' => $second]);
        $reupload->assertOk();
        $secondUrl = $reupload->json('url');

        $this->assertNotSame($firstUrl, $secondUrl);
        Storage::disk('public')->assertMissing('answers/audio/'.basename($firstUrl));
        Storage::disk('public')->assertExists('answers/audio/'.basename($secondUrl));

        $answer->refresh();
        $this->assertSame($secondUrl, $answer->answer_file_url);

        // Xoá để ghi lại — file bị xoá khỏi disk + answer_file_url về null.
        $delete = $this->actingAs($student)->delete($audioUrl);
        $delete->assertOk();
        Storage::disk('public')->assertMissing('answers/audio/'.basename($secondUrl));

        $answer->refresh();
        $this->assertNull($answer->answer_file_url);
    }

    public function test_uploading_audio_for_non_speaking_question_is_rejected(): void
    {
        Storage::fake('public');

        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Reading Sample',
            'slug' => 'reading-sample-for-audio-test',
            'skill' => 'reading',
            'duration_minutes' => 15,
            'total_score' => 10,
            'is_published' => true,
        ]);
        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1]);
        $mc = $section->questions()->create(['order' => 1, 'type' => 'multiple_choice', 'content' => 'Q1', 'score' => 10]);
        $mc->options()->create(['label' => 'A', 'content' => 'A', 'is_correct' => true]);

        $attemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');

        $file = UploadedFile::fake()->create('answer.mp3', 500, 'audio/mpeg');
        $this->actingAs($student)
            ->post("/api/v1/attempts/{$attemptId}/answers/{$mc->id}/audio", ['file' => $file])
            ->assertStatus(422);
    }

    public function test_submitting_a_speaking_test_sets_pending_review_and_teacher_can_grade(): void
    {
        Storage::fake('public');

        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeSpeakingTest($teacher);
        $question = $test->parts->first()->sections->first()->questions->first();

        $attemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');

        $file = UploadedFile::fake()->create('answer.mp3', 500, 'audio/mpeg');
        $this->actingAs($student)
            ->post("/api/v1/attempts/{$attemptId}/answers/{$question->id}/audio", ['file' => $file])
            ->assertOk();

        $submit = $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit");
        $submit->assertOk()->assertJson(['status' => 'pending_review']);

        $attempt = TestAttempt::find($attemptId);
        $this->assertSame('pending_review', $attempt->status);

        $show = $this->actingAs($teacher)->getJson("/api/v1/admin/attempts/{$attemptId}");
        $show->assertOk();
        $answerPayload = collect($show->json('attempt.test.parts.0.sections.0.questions'))
            ->firstWhere('id', $question->id)['answer'];
        $this->assertNotNull($answerPayload['answer_file_url']);

        $grade = $this->actingAs($teacher)->postJson("/api/v1/admin/attempts/{$attemptId}/grade", [
            'answers' => [
                ['question_id' => $question->id, 'score' => 8, 'feedback' => 'Phát âm khá tốt.'],
            ],
        ]);

        $grade->assertOk()->assertJson([
            'attempt' => ['status' => 'graded', 'total_score' => 8],
        ]);

        $answer = $attempt->answers()->first();
        $this->assertNull($answer->is_correct);
        $this->assertEquals(8, $answer->score);
        $this->assertSame($teacher->id, $answer->graded_by);
    }
}
