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

            ActivityLog::create([
                'user_id' => $attempt->user_id,
                'type' => 'test_attempt',
                'subject' => $test->title,
                'score' => $graded['total_score'],
                'created_at' => now(),
            ]);

            $isNewBest = $this->reconcileBestAttempt($attempt, $graded);

            return [
                'total_score' => $graded['total_score'],
                'correct_count' => $graded['correct_count'],
                'question_count' => $graded['question_count'],
                'is_new_best' => $isNewBest,
                'test' => new TestDetailResource($test, revealAnswers: true),
                'answers' => $graded['answers'],
            ];
        });
    }

    /**
     * Duyệt mọi câu hỏi của đề, tự chấm 3 loại (multiple_choice/select/fill_blank),
     * bỏ qua upload (chờ chấm sau). Ghi is_correct + score vào attempt_answers của $attempt.
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

        foreach ($questions as $question) {
            $submitted = $submittedAnswers->get($question->id);

            if ($question->type === QuestionType::Upload) {
                // Câu nói/viết: chờ AI hoặc giáo viên chấm sau, không tự chấm ở đây.
                $isCorrect = null;
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
        ];
    }

    /**
     * Đối chiếu $attempt (lượt vừa chấm) với lượt submitted cao điểm nhất hiện có của
     * (user, test). Giữ lại đúng 1 dòng test_attempts điểm cao hơn, xoá dòng còn lại
     * (kèm attempt_answers). Trả về true nếu $attempt trở thành lượt điểm cao nhất.
     */
    private function reconcileBestAttempt(TestAttempt $attempt, array $graded): bool
    {
        $previousBest = TestAttempt::where('user_id', $attempt->user_id)
            ->where('test_id', $attempt->test_id)
            ->where('status', 'submitted')
            ->where('id', '!=', $attempt->id)
            ->lockForUpdate()
            ->first();

        if (! $previousBest || $graded['total_score'] > (float) $previousBest->total_score) {
            $attemptCount = $previousBest ? $previousBest->attempt_count + 1 : 1;

            if ($previousBest) {
                $previousBest->answers()->delete();
                $previousBest->delete();
            }

            $attempt->update([
                'submitted_at' => now(),
                'status' => 'submitted',
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
