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

            $totalScore = (float) $attempt->answers()->sum('score');
            $correctCount = $attempt->answers()->where('is_correct', true)->count();

            $survived = $this->gradingService->reconcileBestAttempt($attempt, [
                'total_score' => $totalScore,
                'correct_count' => $correctCount,
                'question_count' => $attempt->question_count,
            ], 'graded');

            // Nếu lượt vừa chấm bị dedup xoá (đã có lượt graded/submitted điểm cao hơn), trả về
            // lượt hiện đang là best cho (user, test) để FE vẫn hiển thị được kết quả cuối cùng.
            $final = $survived
                ? $attempt
                : TestAttempt::where('user_id', $attempt->user_id)
                    ->where('test_id', $attempt->test_id)
                    ->whereIn('status', ['submitted', 'graded'])
                    ->firstOrFail();

            return $this->show($final);
        });
    }
}
