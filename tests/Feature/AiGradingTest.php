<?php

namespace Tests\Feature;

use App\Jobs\GradeAttemptWithAi;
use App\Models\AiUsageLog;
use App\Models\AttemptAiSuggestion;
use App\Models\Setting;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Ai\AiConfig;
use App\Services\Ai\AiGradingService;
use App\Services\Ai\GradingResult;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Chấm bài bằng AI.
 *
 * Nhóm test QUAN TRỌNG NHẤT ở đây là nhóm "chưa cấu hình": chưa bật / chưa có khoá / hết
 * hạn mức thì hệ thống phải chạy y hệt như khi chưa có tính năng này — không job, không gọi
 * mạng, bài vẫn vào hàng chờ cô chấm tay.
 */
class AiGradingTest extends TestCase
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

    private function makeWritingTest(bool $aiGrading = true): Test
    {
        $test = Test::create([
            'created_by' => $this->teacher->id, 'title' => 'Đề viết', 'slug' => 'de-viet-'.uniqid(),
            'skill' => 'writing', 'duration_minutes' => 30, 'total_score' => 10,
            'is_published' => true, 'ai_grading' => $aiGrading,
            'rubric' => 'Đúng đề, dùng thì quá khứ, tối thiểu 3 câu.',
        ]);

        $part = $test->parts()->create(['title' => 'Part 1', 'order' => 1]);
        $section = $part->sections()->create(['order' => 1]);
        $section->questions()->create([
            'type' => 'writing', 'content' => 'Write about your last holiday.',
            'order' => 1, 'score' => 10,
        ]);

        return $test;
    }

    /** Nộp một bài viết, trả về id lượt làm. */
    private function submitWriting(Test $test, string $text = 'Last summer I went to Da Nang with my family.'): int
    {
        $questionId = $test->parts()->first()->sections()->first()->questions()->first()->id;

        $attemptId = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")->assertOk()->json('attempt_id');

        $this->actingAs($this->student)->putJson("/api/v1/attempts/{$attemptId}/answers", [
            'answers' => [['question_id' => $questionId, 'answer_text' => $text]],
        ])->assertOk();

        $this->actingAs($this->student)
            ->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        return $attemptId;
    }

    /**
     * Giả lập cô đã bật AI và dán khoá.
     *
     * Ghi THẲNG bảng settings chứ không qua `SettingService::set()`: nhóm `ai` đang để
     * `readonly` (tính năng chờ phát triển, xem config/appsettings.php) nên đường ghi bình
     * thường từ chối. Đường chấm tự động vẫn phải chạy đúng cho ngày mở khoá — đó là thứ
     * các test dưới đây bảo vệ.
     */
    private function enableAi(array $overrides = []): void
    {
        $settings = app(SettingService::class);

        foreach (array_merge(['ai.enabled' => '1', 'ai.api_key' => 'sk-test-key'], $overrides) as $key => $value) {
            $meta = $settings->field($key);

            Setting::updateOrCreate(['key' => $key], [
                'value' => empty($meta['secret'])
                    ? (string) $value
                    : Crypt::encryptString((string) $value),
                'type' => $meta['type'],
                'group' => 'ai',
            ]);
        }

        $settings->flush();
    }

    /* ── Chưa cấu hình: hệ thống phải chạy y như cũ ──────────────────────────── */

    public function test_nothing_happens_when_ai_is_not_configured(): void
    {
        Queue::fake();
        Http::preventStrayRequests();

        $test = $this->makeWritingTest();
        $attemptId = $this->submitWriting($test);

        // Không job nào được đẩy đi, không gọi mạng, bài vẫn chờ cô chấm.
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('attempt_ai_suggestions', 0);
        $this->assertDatabaseHas('test_attempts', ['id' => $attemptId, 'status' => 'pending_review']);
    }

    public function test_nothing_happens_when_enabled_but_no_api_key(): void
    {
        Queue::fake();
        Http::preventStrayRequests();

        $this->enableAi(['ai.api_key' => '']);

        $test = $this->makeWritingTest();
        $this->submitWriting($test);

        Queue::assertNothingPushed();
    }

    public function test_nothing_happens_when_the_test_has_ai_grading_off(): void
    {
        Queue::fake();
        $this->enableAi();

        $test = $this->makeWritingTest(aiGrading: false);
        $this->submitWriting($test);

        Queue::assertNothingPushed();
    }

    public function test_nothing_happens_when_monthly_budget_is_used_up(): void
    {
        Queue::fake();
        $this->enableAi(['ai.monthly_budget_usd' => 1.0]);

        AiUsageLog::create([
            'provider' => 'openai', 'model' => 'gpt-5.4-mini', 'kind' => 'text',
            'input_tokens' => 0, 'output_tokens' => 0, 'cost_usd' => 1.5,
        ]);

        $test = $this->makeWritingTest();
        $this->submitWriting($test);

        Queue::assertNothingPushed();
    }

    /* ── Đã cấu hình: chấm và lưu đề xuất ───────────────────────────────────── */

    public function test_job_is_queued_once_ai_is_configured(): void
    {
        Queue::fake();
        $this->enableAi();

        $test = $this->makeWritingTest();
        $attemptId = $this->submitWriting($test);

        Queue::assertPushed(GradeAttemptWithAi::class, fn ($job) => $job->attemptId === $attemptId);
    }

    public function test_ai_suggestion_is_stored_without_touching_the_real_score(): void
    {
        // Chặn job tự chạy để bài chỉ được chấm đúng một lần (ta gọi tay bên dưới).
        Queue::fake();
        $this->enableAi();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'criteria' => ['task' => 8, 'vocabulary' => 7, 'grammar' => 6, 'coherence' => 7],
                    'score' => 7,
                    'comment' => 'Em viết đúng đề, ý rõ ràng.',
                    'errors' => ['"go" nên đổi thành "went"'],
                ])]]],
                'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 200],
            ]),
        ]);

        $test = $this->makeWritingTest();
        $attemptId = $this->submitWriting($test);

        app(AiGradingService::class)->gradeAttempt(TestAttempt::with('test')->find($attemptId));

        $suggestion = AttemptAiSuggestion::where('test_attempt_id', $attemptId)->firstOrFail();
        $this->assertSame('ok', $suggestion->status);
        $this->assertSame('7.00', $suggestion->score);
        $this->assertStringContainsString('Điểm đề xuất: 7/10', $suggestion->feedback);
        $this->assertStringContainsString('Ngữ pháp: 6', $suggestion->feedback);
        $this->assertStringContainsString('went', $suggestion->feedback);

        // Điểm THẬT chưa bị đụng và học viên vẫn thấy "chờ cô chấm".
        $this->assertDatabaseHas('test_attempts', ['id' => $attemptId, 'status' => 'pending_review']);
        $this->assertDatabaseHas('attempt_answers', ['test_attempt_id' => $attemptId, 'score' => 0]);

        // Có ghi nhận chi phí để đối chiếu hạn mức.
        $this->assertDatabaseCount('ai_usage_logs', 1);
        $this->assertGreaterThan(0, (float) AiUsageLog::first()->cost_usd);
    }

    public function test_api_failure_never_breaks_the_attempt(): void
    {
        // Chặn job tự chạy để bài chỉ được chấm đúng một lần (ta gọi tay bên dưới).
        Queue::fake();
        $this->enableAi();

        Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'quá tải']], 500)]);

        $test = $this->makeWritingTest();
        $attemptId = $this->submitWriting($test);

        app(AiGradingService::class)->gradeAttempt(TestAttempt::with('test')->find($attemptId));

        // Ghi lại là hỏng để cô biết, nhưng lượt làm bài vẫn nguyên vẹn.
        $this->assertDatabaseHas('attempt_ai_suggestions', [
            'test_attempt_id' => $attemptId, 'status' => 'failed',
        ]);
        $this->assertDatabaseHas('test_attempts', ['id' => $attemptId, 'status' => 'pending_review']);
    }

    public function test_student_result_never_exposes_ai_suggestions(): void
    {
        // Chặn job tự chạy để bài chỉ được chấm đúng một lần (ta gọi tay bên dưới).
        Queue::fake();
        $this->enableAi();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['score' => 9, 'comment' => 'Rất tốt'])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]),
        ]);

        $test = $this->makeWritingTest();
        $attemptId = $this->submitWriting($test);
        app(AiGradingService::class)->gradeAttempt(TestAttempt::with('test')->find($attemptId));

        $body = $this->actingAs($this->student)
            ->getJson("/api/v1/attempts/{$attemptId}/result")->assertOk()->content();

        $this->assertStringNotContainsString('Rất tốt', $body);
        $this->assertStringNotContainsString('ai_suggestion', $body);
    }

    public function test_teacher_grading_screen_shows_the_suggestion(): void
    {
        // Chặn job tự chạy để bài chỉ được chấm đúng một lần (ta gọi tay bên dưới).
        Queue::fake();
        $this->enableAi();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'score' => 8, 'comment' => 'Bài viết mạch lạc.',
                ])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]),
        ]);

        $test = $this->makeWritingTest();
        $attemptId = $this->submitWriting($test);
        app(AiGradingService::class)->gradeAttempt(TestAttempt::with('test')->find($attemptId));

        $res = $this->actingAs($this->teacher)
            ->getJson("/api/v1/admin/attempts/{$attemptId}")->assertOk();

        $suggestion = $res->json('attempt.test.parts.0.sections.0.questions.0.ai_suggestion');
        $this->assertSame(8.0, (float) $suggestion['score']);
        $this->assertStringContainsString('Bài viết mạch lạc.', $suggestion['feedback']);
    }

    /**
     * Câu tạo bằng editor có `questions.score = 1` (điểm thật quy đổi về thang đề lúc chấm).
     * AI luôn được hỏi theo thang 10 rồi quy đổi — nếu không, chữ hiện "7.5" mà ô điểm lại
     * nhảy ra 1.00, cô tưởng hệ thống chấm sai.
     */
    public function test_ai_score_is_converted_to_the_question_scale(): void
    {
        Queue::fake();
        $this->enableAi();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'criteria' => ['task' => 8, 'vocabulary' => 7, 'grammar' => 7, 'coherence' => 8],
                    'score' => 7.5,
                    'comment' => 'Bài ổn.',
                ])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]),
        ]);

        $test = $this->makeWritingTest();
        // Đúng như câu do editor tạo ra: thang 1 điểm.
        $test->parts()->first()->sections()->first()->questions()->first()->update(['score' => 1]);

        $attemptId = $this->submitWriting($test);
        app(AiGradingService::class)->gradeAttempt(TestAttempt::with('test')->find($attemptId));

        $suggestion = AttemptAiSuggestion::where('test_attempt_id', $attemptId)->firstOrFail();

        // Điểm lưu đã quy về thang của câu…
        $this->assertSame('0.75', $suggestion->score);
        // …và chữ nói rõ cả hai con số để cô không hiểu nhầm.
        $this->assertStringContainsString('Điểm đề xuất: 7.5/10', $suggestion->feedback);
        $this->assertStringContainsString('điền 0.75', $suggestion->feedback);
    }

    /**
     * Cách cô đang chấm: bấm Copy ở màn chấm → dán sang ChatGPT của mình. Khối chữ phải đủ
     * yêu cầu chấm + đề bài + tiêu chí riêng của cô + bài viết của em, để cô không phải gom tay.
     */
    public function test_teacher_gets_a_ready_made_prompt_to_copy_into_chatgpt(): void
    {
        $test = $this->makeWritingTest();
        $attemptId = $this->submitWriting($test, 'Last summer I went to Hue with my class.');

        $res = $this->actingAs($this->teacher)
            ->getJson("/api/v1/admin/attempts/{$attemptId}")->assertOk();

        $prompt = $res->json('attempt.test.parts.0.sections.0.questions.0.ai_prompt');

        $this->assertNotNull($prompt);
        $this->assertStringContainsString('Write about your last holiday', $prompt);        // đề bài
        $this->assertStringContainsString('Đúng đề, dùng thì quá khứ', $prompt);            // rubric của cô
        $this->assertStringContainsString('Last summer I went to Hue', $prompt);            // bài của em
        $this->assertStringContainsString('Ngữ pháp', $prompt);                              // bộ tiêu chí
    }

    /** Em bỏ trống bài thì không có gì để nhờ chấm — nút Copy phải ẩn. */
    public function test_no_prompt_when_the_student_left_the_answer_blank(): void
    {
        $test = $this->makeWritingTest();
        $attemptId = $this->submitWriting($test, '');

        $res = $this->actingAs($this->teacher)
            ->getJson("/api/v1/admin/attempts/{$attemptId}")->assertOk();

        $this->assertNull($res->json('attempt.test.parts.0.sections.0.questions.0.ai_prompt'));
    }

    /**
     * Nhóm `ai` đang tạm khoá: cô nhìn thấy nhưng không bật được, và API cũng phải từ chối
     * ghi (không chỉ FE disable nút) — nếu không thì chỉ cần gọi thẳng API là mở được.
     */
    public function test_ai_settings_are_locked_until_the_feature_ships(): void
    {
        $super = User::factory()->teacher()->create(['is_super_admin' => true]);

        $this->actingAs($super)
            ->putJson('/api/v1/admin/settings', ['values' => ['ai.enabled' => true]])
            ->assertOk();

        // Đã bỏ qua, không ghi gì.
        $this->assertDatabaseMissing('settings', ['key' => 'ai.enabled']);
        $this->assertFalse((bool) setting('ai.enabled'));
    }

    public function test_budget_estimate_separates_audio_tokens_from_text(): void
    {
        $config = app(AiConfig::class);

        $result = new GradingResult(
            score: 8, feedback: '', criteria: [], model: 'gpt-audio',
            inputTokens: 1_600, outputTokens: 200, audioInputTokens: 900,
        );

        // 700 chữ vào ×$2.5 + 200 chữ ra ×$10 + 900 audio ×$32, tất cả trên 1 triệu token.
        $expected = 700 / 1e6 * 2.5 + 200 / 1e6 * 10 + 900 / 1e6 * 32;

        $this->assertEqualsWithDelta($expected, $config->estimateCost($result), 0.000001);
    }
}
