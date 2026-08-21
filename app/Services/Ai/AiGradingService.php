<?php

namespace App\Services\Ai;

use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\TestAttempt;
use App\Repositories\AiGradingRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Điều phối việc chấm bằng AI cho một lượt làm bài.
 *
 * NGUYÊN TẮC: đây là lớp phụ, hỏng thì hệ thống vẫn phải chạy bình thường.
 *   - chưa bật / chưa có khoá / hết hạn mức  → bỏ qua im lặng, bài về chờ cô chấm tay
 *   - gọi API lỗi ở một câu                  → ghi lại lỗi ở câu đó, các câu khác vẫn chấm
 *   - MỌI ngoại lệ đều bị nuốt và ghi log     → không bao giờ làm hỏng luồng nộp bài
 *
 * AI chỉ ghi vào `attempt_ai_suggestions`. Điểm chính thức (`attempt_answers.score`) và
 * trạng thái lượt làm KHÔNG bị đụng tới — học viên vẫn thấy "Chờ cô chấm" cho tới khi cô
 * duyệt và bấm Lưu ở màn chấm.
 */
final class AiGradingService
{
    public function __construct(
        private readonly AiConfig $config,
        private readonly AiDriverManager $drivers,
        private readonly AudioConvertService $audio,
        private readonly AiGradingRepository $repository,
    ) {}

    /** Có nên chạy AI cho lượt này không — kiểm mọi cổng trước khi tốn một đồng nào. */
    public function shouldGrade(TestAttempt $attempt): bool
    {
        if (! $this->config->enabled() || $this->config->budgetExhausted()) {
            return false;
        }

        if (! $this->drivers->driver()->isReady()) {
            return false;
        }

        // Cô bật AI cho riêng từng đề.
        return (bool) $attempt->test?->ai_grading;
    }

    /**
     * Chấm mọi câu viết/nói của lượt. Không ném ngoại lệ ra ngoài trong bất cứ trường hợp nào.
     */
    public function gradeAttempt(TestAttempt $attempt): void
    {
        try {
            if (! $this->shouldGrade($attempt)) {
                return;
            }

            $test = $this->repository->testWithQuestions($attempt);

            if (! $test) {
                return;
            }

            $answers = $this->repository->answersByQuestion($attempt);
            $driver = $this->drivers->driver();
            $questions = $this->repository->gradableQuestions($test);

            foreach ($questions as $question) {
                // Hết hạn mức giữa chừng → dừng luôn, các câu còn lại để cô chấm tay.
                if ($this->config->budgetExhausted()) {
                    break;
                }

                $this->gradeQuestion($attempt, $test, $question, $answers->get($question->id), $driver);
            }
        } catch (Throwable $e) {
            Log::warning('Chấm AI hỏng cho lượt làm bài.', [
                'attempt_id' => $attempt->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function gradeQuestion(
        TestAttempt $attempt,
        $test,
        Question $question,
        ?AttemptAnswer $answer,
        GradingDriver $driver,
    ): void {
        $isSpeaking = $question->type === QuestionType::Speaking;

        if ($isSpeaking && (! $this->config->gradesSpeaking() || ! $driver->supportsAudio())) {
            return;
        }

        if (! $isSpeaking && ! $this->config->gradesWriting()) {
            return;
        }

        // Em bỏ trống thì không có gì để chấm — cũng không tốn tiền gọi API.
        $hasWork = $isSpeaking
            ? filled($answer?->answer_file_url)
            : filled($answer?->answer_text);

        if (! $hasWork) {
            return;
        }

        $audio = null;

        try {
            $request = $isSpeaking
                ? $this->speakingRequest($question, $test, $answer, $audio)
                : $this->writingRequest($question, $test, $answer);

            if (! $request) {
                return;
            }

            $result = $driver->grade($request);

            $this->repository->saveSuggestion($attempt, $question, [
                'score' => $result->score,
                'feedback' => $result->feedback,
                'criteria' => $result->criteria,
                'provider' => $driver->name(),
                'model' => $result->model,
                'raw_response' => $result->raw,
                'status' => 'ok',
                'error' => null,
            ]);

            $this->repository->logUsage([
                'test_attempt_id' => $attempt->id,
                'provider' => $driver->name(),
                'model' => $result->model,
                'kind' => $isSpeaking ? 'audio' : 'text',
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
                'cost_usd' => $this->config->estimateCost($result),
            ]);
        } catch (Throwable $e) {
            // Ghi lại để cô biết câu này AI chấm hỏng, thay vì im lặng bỏ qua.
            $this->repository->saveSuggestion($attempt, $question, [
                'provider' => $driver->name(),
                'model' => $isSpeaking ? $this->config->audioModel() : $this->config->textModel(),
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            Log::warning('Chấm AI hỏng ở một câu.', [
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($audio) {
                $this->audio->cleanup($audio[0], $audio[2]);
            }
        }
    }

    private function writingRequest(Question $question, $test, AttemptAnswer $answer): GradingRequest
    {
        return new GradingRequest(
            kind: 'writing',
            questionContent: (string) $question->content,
            hint: $question->hint,
            rubric: $test->rubric,
            maxScore: (float) $question->score,
            answerText: strip_tags((string) $answer->answer_text),
            wordLimit: $test->word_limit,
        );
    }

    /**
     * @param  array{0: string, 1: string, 2: bool}|null  $audio  gán ngược để `finally` dọn file tạm
     */
    private function speakingRequest(Question $question, $test, AttemptAnswer $answer, ?array &$audio): ?GradingRequest
    {
        $audio = $this->audio->prepare((string) $answer->answer_file_url);

        if (! $audio) {
            // Thiếu ffmpeg hoặc file hỏng → để cô chấm tay, không coi là lỗi hệ thống.
            return null;
        }

        return new GradingRequest(
            kind: 'speaking',
            questionContent: (string) $question->content,
            hint: $question->hint,
            rubric: $test->rubric,
            maxScore: (float) $question->score,
            audioPath: $audio[0],
            audioFormat: $audio[1],
        );
    }
}
