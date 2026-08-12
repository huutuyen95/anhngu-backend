<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Chấm tay bài làm (chủ yếu câu writing) — tách khỏi TestGradingService (chấm tự động lúc nộp bài).
 */
class AttemptGradingService
{
    public function __construct(private readonly TestGradingService $gradingService) {}

    /**
     * Danh sách bài làm cần/đã chấm — mặc định `status=pending_review` ("Chờ chấm").
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return TestAttempt::query()
            ->where('status', $filters['status'] ?? 'pending_review')
            ->when($filters['classroom_id'] ?? null, fn ($q, $id) => $q->where('classroom_id', $id))
            ->when($filters['test_id'] ?? null, fn ($q, $id) => $q->where('test_id', $id))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->with(['user:id,name,email', 'test:id,title,skill'])
            ->orderByDesc('submitted_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function show(TestAttempt $attempt): TestAttempt
    {
        return $attempt->load([
            'user:id,name,email',
            'test',
            'test.parts' => fn ($q) => $q->orderBy('order'),
            'test.parts.sections' => fn ($q) => $q->orderBy('order'),
            'test.parts.sections.questions' => fn ($q) => $q->orderBy('order'),
            'test.parts.sections.questions.options',
            'answers.gradedBy:id,name',
        ]);
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
        return DB::transaction(function () use ($attempt, $answers, $teacher) {
            $questions = Question::whereIn('id', collect($answers)->pluck('question_id'))
                ->get()
                ->keyBy('id');

            foreach ($answers as $row) {
                $question = $questions->get($row['question_id']);
                if (! $question) {
                    continue;
                }

                $score = min((float) $row['score'], (float) $question->score);

                AttemptAnswer::updateOrCreate(
                    ['test_attempt_id' => $attempt->id, 'question_id' => $question->id],
                    [
                        'score' => $score,
                        'is_correct' => in_array($question->type, [QuestionType::Writing, QuestionType::Speaking], true)
                            ? null
                            : $score >= (float) $question->score,
                        'feedback' => $row['feedback'] ?? null,
                        'graded_by' => $teacher->id,
                        'graded_at' => now(),
                    ]
                );
            }

            $correctCount = $attempt->answers()->where('is_correct', true)->count();

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

            return $this->show($attempt);
        });
    }

    /**
     * Tổng điểm sau khi chấm tay, quy về thang điểm của đề:
     *   (tổng điểm câu đã chấm / tổng điểm tối đa của đề) × test.total_score
     */
    private function scaleToTestScore(TestAttempt $attempt): float
    {
        $test = $attempt->test()->with('parts.sections.questions')->firstOrFail();

        $maxScore = (float) $test->parts
            ->flatMap(fn ($part) => $part->sections)
            ->flatMap(fn ($section) => $section->questions)
            ->sum('score');

        if ($maxScore <= 0) {
            return 0.0;
        }

        $rawScore = (float) $attempt->answers()->sum('score');

        return round($rawScore / $maxScore * (float) $test->total_score, 2);
    }
}
