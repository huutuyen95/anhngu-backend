<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Danh sách đề cho khu học viên ("Thư viện → Đề thi") — chỉ đề đã publish, kèm số câu,
 * nhãn thư mục và tình trạng lượt làm của chính học viên đó.
 *
 * Song song với AdminTestService (khu giáo viên) nhưng khác hẳn về dữ liệu trả ra:
 * không lộ cấu hình đề, và luôn gắn kèm attempt của user hiện tại.
 */
class StudentTestService
{
    /** Các trạng thái coi là "đã nộp" — xem TestAttempt::status. */
    private const SUBMITTED_STATUSES = ['pending_review', 'submitted', 'graded'];

    /** Lượt đã chốt điểm (pending_review mới có điểm tạm của phần tự chấm). */
    private const FINALIZED_STATUSES = ['submitted', 'graded'];

    /**
     * @return Collection<int, Test> mỗi Test được gắn thêm `question_count` + `attempt_summary`
     */
    public function list(User $student): Collection
    {
        $tests = Test::query()
            ->where('is_published', true)
            // Nhãn nhóm trên card: "<lớp> / <thư mục cha> / <thư mục>".
            ->with([
                'category:id,name,parent_id,classroom_id',
                'category.parent:id,name',
                'category.classroom:id,name',
            ])
            ->get();

        $questionCounts = Question::countsByTest($tests->pluck('id'));
        $attemptsByTest = $this->attemptsOf($student, $tests->pluck('id'));

        return $tests->each(function (Test $test) use ($questionCounts, $attemptsByTest) {
            $attempts = $attemptsByTest->get($test->id);

            $test->setAttribute('question_count', (int) ($questionCounts[$test->id] ?? 0));
            $test->setAttribute('attempt_summary', $attempts ? $this->attemptSummary($attempts) : null);
        });
    }

    /**
     * Lượt đã nộp của học viên, gom theo test_id.
     *
     * Phải lấy đủ 3 trạng thái: đề có câu writing/speaking KHÔNG bao giờ thành `submitted` mà đi
     * `pending_review` → `graded`. Và gom (thay vì keyBy) vì `pending_review` không bị dedup
     * (TestGradingService::markPendingReview) nên có thể nằm cạnh một lượt `graded` cũ.
     *
     * @param  Collection<int, int>  $testIds
     * @return Collection<int, Collection<int, TestAttempt>>
     */
    private function attemptsOf(User $student, Collection $testIds): Collection
    {
        if ($testIds->isEmpty()) {
            return collect();
        }

        return TestAttempt::query()
            ->where('user_id', $student->id)
            ->whereIn('status', self::SUBMITTED_STATUSES)
            ->whereIn('test_id', $testIds)
            ->get()
            ->groupBy('test_id');
    }

    /**
     * Gộp các lượt còn lại của một đề thành 1 dòng cho card.
     *
     * `status` lấy theo lượt mới nhất. `best_score` CHỈ lấy từ lượt đã finalize — lượt
     * `pending_review` mới có điểm tạm của phần tự chấm, chưa cộng điểm cô chấm tay, trả ra sẽ
     * thành điểm thấp giả. Đề chờ chấm lần đầu → `best_score` null.
     *
     * @param  Collection<int, TestAttempt>  $attempts
     * @return array<string, mixed>
     */
    private function attemptSummary(Collection $attempts): array
    {
        $latest = $attempts->sortByDesc('last_attempted_at')->first();
        $bestScore = $attempts
            ->whereIn('status', self::FINALIZED_STATUSES)
            ->max(fn (TestAttempt $attempt) => (float) $attempt->total_score);

        return [
            'status' => $latest->status,
            'best_score' => $bestScore !== null ? (float) $bestScore : null,
            'attempt_count' => (int) $attempts->max('attempt_count'),
            'last_attempted_at' => $latest->last_attempted_at,
        ];
    }
}
