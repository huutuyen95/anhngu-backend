<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Soạn câu NÓI: đề bài + gợi ý text (`hint`) + ảnh gợi ý (`images`) + giới hạn ghi âm.
 *
 * `hint` khác `explanation`: gợi ý phải đến tay học viên NGAY LÚC làm bài, còn lời giải chỉ
 * lộ sau khi nộp — nên nó không được nằm sau cờ `revealAnswers`.
 */
class SpeakingQuestionTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->create();
    }

    private function makeSpeakingTest(): Test
    {
        return Test::create([
            'created_by' => $this->teacher->id, 'title' => 'Đề nói', 'slug' => 'de-noi-'.uniqid(),
            'skill' => 'speaking', 'duration_minutes' => 15, 'total_score' => 10, 'is_published' => true,
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function saveSpeakingStructure(Test $test, array $extra = []): TestResponse
    {
        return $this->actingAs($this->teacher)->putJson("/api/v1/admin/tests/{$test->id}/structure", [
            'parts' => [[
                'order' => 0,
                'title' => 'Part 1',
                'sections' => [[
                    'order' => 0,
                    'instruction' => 'Nhìn tranh và nói',
                    'questions' => [array_merge([
                        'order' => 0,
                        'type' => 'speaking',
                        'content' => 'Describe your last holiday.',
                        'hint' => "You should say:\n- where you went\n- who you went with",
                        'images' => ['http://localhost/storage/covers/beach.jpg', 'http://localhost/storage/covers/hotel.jpg'],
                        'record_limit_seconds' => 90,
                        'options' => [],
                    ], $extra)],
                ]],
            ]],
        ]);
    }

    public function test_teacher_can_save_a_speaking_question_with_hint_and_images(): void
    {
        $test = $this->makeSpeakingTest();

        $res = $this->saveSpeakingStructure($test)->assertOk();

        $question = $res->json('test.parts.0.sections.0.questions.0');
        $this->assertSame('speaking', $question['type']);
        $this->assertSame("You should say:\n- where you went\n- who you went with", $question['hint']);
        $this->assertCount(2, $question['images']);
        $this->assertSame(90, $question['record_limit_seconds']);

        $this->assertDatabaseHas('questions', [
            'type' => 'speaking',
            'content' => 'Describe your last holiday.',
            'record_limit_seconds' => 90,
        ]);
    }

    public function test_student_sees_hint_and_images_while_taking_the_test(): void
    {
        $test = $this->makeSpeakingTest();
        $this->saveSpeakingStructure($test)->assertOk();

        // `revealAnswers: false` — gợi ý VẪN phải có, khác lời giải.
        $res = $this->actingAs($this->student)->getJson("/api/v1/tests/{$test->id}")->assertOk();

        $question = $res->json('parts.0.sections.0.questions.0');
        $this->assertStringContainsString('where you went', $question['hint']);
        $this->assertCount(2, $question['images']);
        $this->assertSame(90, $question['record_limit_seconds']);
        $this->assertArrayNotHasKey('explanation', $question);
    }

    public function test_record_limit_out_of_range_is_rejected(): void
    {
        $test = $this->makeSpeakingTest();

        $this->saveSpeakingStructure($test, ['record_limit_seconds' => 900])
            ->assertStatus(422);
    }

    /**
     * Học viên nộp bài nói từ nhiều loại thiết bị: Chrome/Android ghi ra webm, Safari trên
     * iPhone/iPad ghi ra mp4/m4a, Firefox ghi ra ogg. Thiếu định dạng nào là thiết bị đó 422.
     */
    public function test_audio_upload_accepts_every_browser_recording_format(): void
    {
        Storage::fake('public');

        $test = $this->makeSpeakingTest();
        $this->saveSpeakingStructure($test)->assertOk();

        $questionId = $test->parts()->first()->sections()->first()->questions()->first()->id;

        $attemptId = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->assertOk()->json('attempt_id');

        foreach (['bai-noi.webm', 'bai-noi.mp4', 'bai-noi.m4a', 'bai-noi.ogg', 'bai-noi.3gp'] as $name) {
            $this->actingAs($this->student)
                ->postJson("/api/v1/attempts/{$attemptId}/answers/{$questionId}/audio", [
                    'file' => UploadedFile::fake()->create($name, 128),
                ])
                ->assertOk()
                ->assertJsonStructure(['url']);
        }

        $this->assertDatabaseHas('attempt_answers', [
            'test_attempt_id' => $attemptId,
            'question_id' => $questionId,
        ]);
    }

    /**
     * Màn thi và màn kết quả của HỌC VIÊN đều phải thấy `answer_file_url`: thiếu ở màn thi
     * thì reload giữa chừng tưởng em chưa ghi âm, thiếu ở màn kết quả thì không nghe lại được.
     */
    public function test_student_endpoints_return_the_recorded_audio(): void
    {
        Storage::fake('public');

        $test = $this->makeSpeakingTest();
        $this->saveSpeakingStructure($test)->assertOk();

        $questionId = $test->parts()->first()->sections()->first()->questions()->first()->id;

        $attemptId = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")->assertOk()->json('attempt_id');

        $url = $this->actingAs($this->student)
            ->postJson("/api/v1/attempts/{$attemptId}/answers/{$questionId}/audio", [
                'file' => UploadedFile::fake()->create('bai-noi.webm', 128),
            ])->assertOk()->json('url');

        // Đang làm dở → GET /attempts/{id} phải trả lại bản ghi.
        $this->actingAs($this->student)
            ->getJson("/api/v1/attempts/{$attemptId}")->assertOk()
            ->assertJsonPath('answers.0.answer_file_url', $url);

        $this->actingAs($this->student)
            ->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        // Đã nộp → màn kết quả nghe lại được.
        $this->actingAs($this->student)
            ->getJson("/api/v1/attempts/{$attemptId}/result")->assertOk()
            ->assertJsonPath('answers.0.answer_file_url', $url);
    }

    public function test_student_can_delete_the_recording_to_try_again(): void
    {
        Storage::fake('public');

        $test = $this->makeSpeakingTest();
        $this->saveSpeakingStructure($test)->assertOk();

        $questionId = $test->parts()->first()->sections()->first()->questions()->first()->id;

        $attemptId = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")->assertOk()->json('attempt_id');

        $this->actingAs($this->student)
            ->postJson("/api/v1/attempts/{$attemptId}/answers/{$questionId}/audio", [
                'file' => UploadedFile::fake()->create('bai-noi.webm', 128),
            ])->assertOk();

        $this->actingAs($this->student)
            ->deleteJson("/api/v1/attempts/{$attemptId}/answers/{$questionId}/audio")->assertOk();

        $this->actingAs($this->student)
            ->getJson("/api/v1/attempts/{$attemptId}")->assertOk()
            ->assertJsonPath('answers.0.answer_file_url', null);
    }

    public function test_teacher_grading_screen_gets_hint_and_images(): void
    {
        Storage::fake('public');

        $test = $this->makeSpeakingTest();
        $this->saveSpeakingStructure($test)->assertOk();

        $questionId = $test->parts()->first()->sections()->first()->questions()->first()->id;

        $attemptId = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")->assertOk()->json('attempt_id');

        $this->actingAs($this->student)
            ->postJson("/api/v1/attempts/{$attemptId}/answers/{$questionId}/audio", [
                'file' => UploadedFile::fake()->create('bai-noi.webm', 128),
            ])->assertOk();

        // Đề có câu nói → nộp xong là chờ cô chấm, không tự chấm.
        $this->actingAs($this->student)
            ->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk()
            ->assertJsonPath('status', 'pending_review');

        $res = $this->actingAs($this->teacher)
            ->getJson("/api/v1/admin/attempts/{$attemptId}")->assertOk();

        $question = $res->json('attempt.test.parts.0.sections.0.questions.0');
        $this->assertStringContainsString('where you went', $question['hint']);
        $this->assertCount(2, $question['images']);
        $this->assertNotNull($question['answer']['answer_file_url']);
    }
}
