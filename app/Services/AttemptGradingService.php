<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Notifications\AttemptGraded;
use App\Models\Question;
use App\Models\TestAttempt;
use App\Models\User;
use App\Repositories\AttemptRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Chấm tay bài làm (chủ yếu câu writing) — tách khỏi TestGradingService (chấm tự động lúc nộp bài).
 */
class AttemptGradingService
{
    public function __construct(
        private readonly TestGradingService $gradingService,
        private readonly AttemptRepository $attempts,
    ) {}

    /**
     * Danh sách bài làm cần/đã chấm. KHÔNG có `status` → trả mọi trạng thái (tab "Tất cả"
     * bên FE không gửi tham số này); tab "Chờ chấm" tự gửi `status=pending_review`.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->attempts->paginateForGrading($filters);
    }

    public function show(TestAttempt $attempt): TestAttempt
    {
        return $this->attempts->loadForGrading($attempt);
    }

    /**
     * Nhập điểm + nhận xét cho từng câu (writing và/hoặc sửa điểm câu khách quan), tính lại
     * total_score, set status=graded. Sau đó chạy lại đối chiếu lượt-điểm-cao-nhất mà
     * TestGradingService::submit() đã bỏ qua lúc nộp bài (vì writing chưa có điểm finalize).
     *
     * @param  array<int, array{question_id:int, score:float, feedback?:string|null}>  $answers
     */
    public function grade(TestAttempt $attempt, array $answers, User $teacher): TestAttempt
    {
        return $this->attempts->transaction(function () use ($attempt, $answers, $teacher) {
            $questions = $this->attempts->questions(collect($answers)->pluck('question_id'));

            foreach ($answers as $row) {
                $question = $questions->get($row['question_id']);
                if (! $question) {
                    continue;
                }

                $score = min((float) $row['score'], (float) $question->score);

                $this->attempts->gradeAnswer($attempt, $question, [
                    'score' => $score,
                    'is_correct' => in_array($question->type, [QuestionType::Writing, QuestionType::Speaking], true)
                        ? null
                        : $score >= (float) $question->score,
                    'feedback' => $row['feedback'] ?? null,
                    'graded_by' => $teacher->id,
                    'graded_at' => now(),
                ]);
            }

            $correctCount = $this->attempts->correctAnswerCount($attempt);

            // Quy về thang điểm của đề, GIỐNG chấm tự động. Trước đây cộng thô
            // attempt_answers.score (editor luôn lưu question.score = 1) nên writing
            // đủ điểm vẫn ra 1 trong khi đề để thang 10.
            $totalScore = $this->scaleToTestScore($attempt);

            // Chấm tay thì luôn giữ lượt vừa chấm và xoá lượt cũ, KHÔNG so điểm: điểm
            // lượt cũ có thể còn ở thang cũ nên so là vô nghĩa. Điểm cuối cùng của học
            // viên = điểm cô chấm gần nhất.
            $this->gradingService->reconcileBestAttempt($attempt, [
                'total_score' => $totalScore,
                'correct_count' => $correctCount,
                'question_count' => $attempt->question_count,
            ], 'graded', keepLatest: true);

            // Báo cho học sinh: bài đã được cô chấm (theo cấu hình notify.web + notify.on_graded).
            if (setting('notify.web', true) && setting('notify.on_graded', true)) {
                $attempt->user?->notify(new AttemptGraded(
                    $attempt->test_id, $attempt->id, $attempt->test?->title ?? 'Đề thi',
                    (float) $totalScore, $attempt->classroom_id, $teacher->name,
                ));
            }

            return $this->show($attempt);
        });
    }

    /**
     * Tổng điểm sau khi chấm tay, quy về thang điểm của đề:
     *   (tổng điểm câu đã chấm / tổng điểm tối đa của đề) × test.total_score
     */
    private function scaleToTestScore(TestAttempt $attempt): float
    {
        $test = $this->attempts->gradingTest($attempt);

        $maxScore = (float) $test->parts
            ->flatMap(fn ($part) => $part->sections)
            ->flatMap(fn ($section) => $section->questions)
            ->sum('score');

        if ($maxScore <= 0) {
            return 0.0;
        }

        $rawScore = $this->attempts->rawScore($attempt);

        return round($rawScore / $maxScore * (float) $test->total_score, 2);
    }
}
