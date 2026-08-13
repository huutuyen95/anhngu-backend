<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Http\Resources\TestDetailResource;
use App\Models\ActivityLog;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use Illuminate\Support\Facades\DB;

class TestGradingService
{
    /**
     * Chấm lượt làm bài `$attempt` (đang in_progress), rồi đối chiếu với lượt `submitted`
     * cao điểm nhất hiện có của (user, test) — mỗi (user, test) chỉ giữ lại 1 dòng
     * test_attempts = lượt điểm cao nhất, lượt còn lại (kèm attempt_answers) bị xoá.
     *
     * Trả về payload đầy đủ của LƯỢT VỪA NỘP (dù lượt đó có bị xoá vì thấp điểm hơn),
     * để FE vẫn hiển thị được kết quả ngay sau khi nộp.
     *
     * @return array{total_score: float, correct_count: int, question_count: int, is_new_best: bool, test: mixed, answers: mixed}
     */
    public function submit(TestAttempt $attempt): array
    {
        return DB::transaction(function () use ($attempt) {
            $test = $attempt->test()->with('parts.sections.questions.options')->firstOrFail();

            $graded = $this->gradeQuestions($attempt, $test);

            // Log MỌI lượt nộp (kể cả lượt sắp bị dedup xoá) — đây là nguồn duy nhất để tính
            // điểm TB / tổng lượt làm của một đề.
            ActivityLog::create([
                'user_id' => $attempt->user_id,
                'test_id' => $test->id,
                'type' => 'test_attempt',
                'subject' => $test->title,
                'score' => $graded['total_score'],
                'created_at' => now(),
            ]);

            // Đề có câu writing chưa chấm → chờ giáo viên chấm tay, không dedup/finalize điểm.
            if ($graded['has_pending_review']) {
                $this->markPendingReview($attempt, $graded);
                $isNewBest = false;
                $status = 'pending_review';
            } else {
                $isNewBest = $this->reconcileBestAttempt($attempt, $graded);
                $status = 'submitted';
            }

            $this->completeMission($attempt);

            return [
                'total_score' => $graded['total_score'],
                'correct_count' => $graded['correct_count'],
                'question_count' => $graded['question_count'],
                'is_new_best' => $isNewBest,
                'status' => $status,
                'test' => new TestDetailResource($test, revealAnswers: true),
                'answers' => $graded['answers'],
            ];
        });
    }

    /**
     * Duyệt mọi câu hỏi của đề, tự chấm 3 loại (multiple_choice/select/fill_blank),
     * bỏ qua upload/writing (chờ chấm sau). Ghi is_correct + score vào attempt_answers của $attempt.
     */
    private function gradeQuestions(TestAttempt $attempt, Test $test): array
    {
        $questions = $test->parts
            ->flatMap(fn ($part) => $part->sections)
            ->flatMap(fn ($section) => $section->questions);

        $submittedAnswers = $attempt->answers()->get()->keyBy('question_id');

        $gradableCount = 0;
        $correctCount = 0;
        $answerRows = [];
        $hasPendingReview = false;

        foreach ($questions as $question) {
            $submitted = $submittedAnswers->get($question->id);

            if (in_array($question->type, [QuestionType::Upload, QuestionType::Writing, QuestionType::Speaking], true)) {
                // Câu nói/viết: chờ AI hoặc giáo viên chấm sau, không tự chấm ở đây.
                $isCorrect = null;

                if (in_array($question->type, [QuestionType::Writing, QuestionType::Speaking], true)) {
                    $hasPendingReview = true;
                }
            } else {
                $gradableCount++;
                $isCorrect = $this->isAnswerCorrect($question, $submitted);

                if ($isCorrect) {
                    $correctCount++;
                }
            }

            $answer = AttemptAnswer::updateOrCreate(
                [
                    'test_attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                ],
                [
                    'question_option_id' => $submitted?->question_option_id,
                    'answer_text' => $submitted?->answer_text,
                    'is_correct' => $isCorrect,
                    'score' => $isCorrect ? $question->score : 0,
                ]
            );

            $answerRows[] = [
                'question_id' => $answer->question_id,
                'question_option_id' => $answer->question_option_id,
                'answer_text' => $answer->answer_text,
                'is_correct' => $answer->is_correct,
            ];
        }

        $totalScore = $gradableCount > 0
            ? round(($correctCount / $gradableCount) * (float) $test->total_score, 2)
            : 0.0;

        return [
            'total_score' => $totalScore,
            'correct_count' => $correctCount,
            'question_count' => $questions->count(),
            'answers' => collect($answerRows)->values(),
            'has_pending_review' => $hasPendingReview,
        ];
    }

    /**
     * Nộp bài giao xong → đóng nhiệm vụ tương ứng (kể cả khi còn chờ cô chấm: với học viên thì
     * nhiệm vụ đã hoàn thành). Lượt tự luyện ở thư viện KHÔNG có mission nên không đụng gì.
     *
     * Trước đây không ai cập nhật missions.status cho đề thi (chỉ deck/document có), khiến báo
     * cáo lớp đếm "bài hoàn thành" theo missions luôn thiếu, lệch với % tiến độ trên roadmap.
     */
    private function completeMission(TestAttempt $attempt): void
    {
        if (! $attempt->mission_id) {
            return;
        }

        $mission = $attempt->mission()->first();

        if (! $mission || $mission->status === 'done') {
            return;
        }

        $mission->update(['status' => 'done', 'completed_at' => now()]);

        if ($mission->classroom) {
            app(ClassroomStatsService::class)->forget($mission->classroom);
        }
    }

    /**
     * Đề có câu writing chưa chấm: lưu lại điểm-tạm (chỉ từ câu tự chấm) + status=pending_review,
     * KHÔNG đối chiếu/xoá so với các lượt khác — chờ giáo viên chấm xong mới finalize
     * qua reconcileBestAttempt().
     */
    private function markPendingReview(TestAttempt $attempt, array $graded): void
    {
        $attempt->update([
            'submitted_at' => now(),
            'status' => 'pending_review',
            'total_score' => $graded['total_score'],
            'correct_count' => $graded['correct_count'],
            'question_count' => $graded['question_count'],
            'last_attempted_at' => now(),
        ]);
    }

    /**
     * Đối chiếu $attempt (lượt vừa chấm xong, điểm đã finalize) với lượt `submitted`/`graded`
     * cao điểm nhất hiện có của (user, test). Giữ lại đúng 1 dòng test_attempts điểm cao hơn,
     * xoá dòng còn lại (kèm attempt_answers). Trả về true nếu $attempt trở thành lượt điểm cao nhất.
     *
     * Public vì AttemptGradingService cũng gọi lại hàm này sau khi giáo viên chấm xong writing
     * (lúc đó điểm mới được finalize, trước đó bị TestGradingService::submit() bỏ qua bước này).
     *
     * $keepLatest = true: luôn giữ $attempt và xoá lượt cũ, không so điểm — dùng cho
     * đường chấm tay, nơi điểm lượt cũ có thể còn ở thang điểm cũ nên so là vô nghĩa.
     */
    public function reconcileBestAttempt(
        TestAttempt $attempt,
        array $graded,
        string $finalStatus = 'submitted',
        bool $keepLatest = false,
    ): bool {
        // CHỈ so trong cùng nguồn (cùng mission, hoặc cùng là lượt tự luyện). Trước đây so
        // trên toàn bộ (user, test) nên lượt tự luyện điểm cao ở thư viện xoá mất bài cô giao.
        $previousBest = TestAttempt::whereIn('status', ['submitted', 'graded'])
            ->sameScope($attempt)
            ->where('id', '!=', $attempt->id)
            ->lockForUpdate()
            ->first();

        if (! $previousBest || $keepLatest || $graded['total_score'] > (float) $previousBest->total_score) {
            $attemptCount = $previousBest ? $previousBest->attempt_count + 1 : $attempt->attempt_count;

            if ($previousBest) {
                $previousBest->answers()->delete();
                $previousBest->delete();
            }

            $attempt->update([
                'submitted_at' => $attempt->submitted_at ?? now(),
                'status' => $finalStatus,
                'total_score' => $graded['total_score'],
                'correct_count' => $graded['correct_count'],
                'question_count' => $graded['question_count'],
                'attempt_count' => $attemptCount,
                'last_attempted_at' => now(),
            ]);

            return true;
        }

        // Lượt vừa làm không cao điểm hơn best cũ → giữ best cũ, xoá lượt vừa làm.
        $previousBest->update([
            'attempt_count' => $previousBest->attempt_count + 1,
            'last_attempted_at' => now(),
        ]);

        $attempt->answers()->delete();
        $attempt->delete();

        return false;
    }

    private function isAnswerCorrect(Question $question, ?AttemptAnswer $answer): bool
    {
        if (! $answer) {
            return false;
        }

        return match ($question->type) {
            QuestionType::MultipleChoice, QuestionType::Select => $this->isOptionCorrect($question, $answer),
            QuestionType::FillBlank => $this->isFillBlankCorrect($question, $answer),
            default => false,
        };
    }

    private function isOptionCorrect(Question $question, AttemptAnswer $answer): bool
    {
        if (! $answer->question_option_id) {
            return false;
        }

        return $question->options
            ->where('is_correct', true)
            ->pluck('id')
            ->contains($answer->question_option_id);
    }

    private function isFillBlankCorrect(Question $question, AttemptAnswer $answer): bool
    {
        $submitted = trim(mb_strtolower((string) $answer->answer_text));

        if ($submitted === '') {
            return false;
        }

        return $question->options
            ->where('is_correct', true)
            ->contains(fn ($option) => trim(mb_strtolower($option->content)) === $submitted);
    }
}
