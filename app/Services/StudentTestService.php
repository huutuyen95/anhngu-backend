<?php

namespace App\Services;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Repositories\StudentTestRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Danh sách đề cho khu học viên ("Thư viện → Đề thi") — chỉ đề đã publish, kèm số câu,
 * nhãn thư mục, chỉ số tổng hợp và tình trạng lượt làm của chính học viên đó.
 *
 * Song song với AdminTestService (khu giáo viên) nhưng khác hẳn về dữ liệu trả ra:
 * không lộ cấu hình đề, và luôn gắn kèm attempt của user hiện tại.
 */
class StudentTestService
{
    public function __construct(private readonly StudentTestRepository $tests) {}

    /** Lượt đã nộp — xem TestAttempt::status. */
    private const SUBMITTED_STATUSES = ['pending_review', 'submitted', 'graded'];

    /** Lượt đã chốt điểm (pending_review mới có điểm tạm của phần tự chấm). */
    private const FINALIZED_STATUSES = ['submitted', 'graded'];

    /** Trạng thái attempt → nhóm lọc ở sidebar FE. Không có lượt nào = `todo`. */
    private const BUCKET_OF_STATUS = [
        'in_progress' => 'doing',
        'pending_review' => 'grading',
        'submitted' => 'done',
        'graded' => 'done',
    ];

    private const BUCKETS = ['todo', 'doing', 'done', 'grading'];

    private const SORTS = ['newest', 'popular', 'name'];

    private const PER_PAGE_MAX = 50;

    /**
     * @param  array<string, mixed>  $filters  q · skill · status · sort · page · per_page
     * @return LengthAwarePaginator<int, Test> mỗi Test được gắn `question_count` + `attempt_summary`
     */
    public function list(array $filters, User $student): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? '', self::SORTS, true) ? $filters['sort'] : 'newest';
        $page = $this->tests->paginate($filters, $student, $this->requestedBuckets($filters), $this->perPage($filters), $sort);

        $tests = $page->getCollection();
        $questionCounts = $this->tests->questionCounts($tests->pluck('id'));
        $attemptsByTest = $this->attemptsOf($student, $tests->pluck('id'));

        $tests->each(function (Test $test) use ($questionCounts, $attemptsByTest) {
            $attempts = $attemptsByTest->get($test->id);

            $test->setAttribute('question_count', (int) ($questionCounts[$test->id] ?? 0));
            $test->setAttribute('attempt_summary', $attempts ? $this->attemptSummary($attempts) : null);
        });

        return $page;
    }

    public function detail(Test $test): Test
    {
        abort_unless($test->is_published, 404);

        return $this->tests->detail($test);
    }

    /**
     * Tóm tắt các lượt em đã làm đề này — cùng hình dạng với `attempt_summary` ở danh sách.
     * Trang chi tiết cần nó để hiện "Xem kết quả lần trước" / "Tiếp tục bài đang làm dở".
     *
     * @return array<string, mixed>|null null = em chưa làm lần nào
     */
    public function attemptFor(User $student, Test $test): ?array
    {
        $attempts = $this->attemptsOf($student, collect([$test->id]))->get($test->id);

        return $attempts ? $this->attemptSummary($attempts) : null;
    }

    /**
     * Số đếm cho hub card + badge lọc trạng thái. Tính trên toàn bộ đề khớp `q`/`skill`
     * (KHÔNG áp `status`) để badge vẫn hiện đủ 4 nhóm khi đang lọc theo một nhóm.
     *
     * @param  array<string, mixed>  $filters
     * @return array{new_this_week: int, status_counts: array<string, int>}
     */
    public function summary(array $filters, User $student): array
    {
        $testIds = $this->tests->matchingIds($filters);

        $counts = array_fill_keys(self::BUCKETS, 0);

        foreach ($this->attemptsOf($student, $testIds) as $attempts) {
            $counts[$this->bucketOf($attempts)]++;
        }

        // Đề chưa có lượt nào của học viên này = "Chưa làm".
        $counts['todo'] = $testIds->count() - ($counts['doing'] + $counts['done'] + $counts['grading']);

        return [
            'new_this_week' => $this->tests->newThisWeek($filters),
            'status_counts' => $counts,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, string>
     */
    private function requestedBuckets(array $filters): Collection
    {
        $raw = $filters['status'] ?? null;

        return collect(is_array($raw) ? $raw : explode(',', (string) $raw))
            ->map(fn ($bucket) => trim((string) $bucket))
            ->filter(fn ($bucket) => in_array($bucket, self::BUCKETS, true))
            ->unique()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(1, min((int) ($filters['per_page'] ?? 12), self::PER_PAGE_MAX));
    }

    /**
     * Lượt làm của học viên trên các đề, gom theo test_id.
     *
     * Phải lấy đủ các trạng thái: đề có câu writing/speaking KHÔNG bao giờ thành `submitted` mà đi
     * `pending_review` → `graded`. Và gom (thay vì keyBy) vì một đề có thể có nhiều dòng cùng lúc:
     * `pending_review` không bị dedup (TestGradingService::markPendingReview), còn `in_progress`
     * là lượt mới bắt đầu nằm cạnh lượt cũ đã nộp.
     *
     * @param  Collection<int, int>  $testIds
     * @return Collection<int, Collection<int, TestAttempt>>
     */
    private function attemptsOf(User $student, Collection $testIds): Collection
    {
        if ($testIds->isEmpty()) {
            return collect();
        }

        return $this->tests->attempts($student, $testIds, array_keys(self::BUCKET_OF_STATUS));
    }

    /**
     * Lượt đại diện cho card: đang làm dở được ưu tiên (CTA "Tiếp tục"), còn lại lấy lượt mới nhất.
     *
     * @param  Collection<int, TestAttempt>  $attempts
     */
    private function currentAttempt(Collection $attempts): TestAttempt
    {
        return $attempts->firstWhere('status', 'in_progress')
            ?? $attempts->sortByDesc('last_attempted_at')->first();
    }

    /**
     * @param  Collection<int, TestAttempt>  $attempts
     */
    private function bucketOf(Collection $attempts): string
    {
        return self::BUCKET_OF_STATUS[$this->currentAttempt($attempts)->status];
    }

    /**
     * Gộp các lượt của một đề thành 1 dòng cho card.
     *
     * `best_score` CHỈ lấy từ lượt đã finalize — lượt `pending_review` mới có điểm tạm của phần tự
     * chấm, chưa cộng điểm cô chấm tay, trả ra sẽ thành điểm thấp giả. Đề chờ chấm lần đầu → null.
     *
     * @param  Collection<int, TestAttempt>  $attempts
     * @return array<string, mixed>
     */
    private function attemptSummary(Collection $attempts): array
    {
        $current = $this->currentAttempt($attempts);
        $inProgress = $current->status === 'in_progress';

        $bestScore = $attempts
            ->whereIn('status', self::FINALIZED_STATUSES)
            ->max(fn (TestAttempt $attempt) => (float) $attempt->total_score);

        // Lượt ĐÃ NỘP gần nhất — khác `id` khi em đang làm dở một lượt mới. Có nó thì
        // trang chi tiết vẫn mở được kết quả lần trước trong lúc lượt mới còn dang dở
        // (mở result của lượt in_progress sẽ 404).
        $lastResult = $attempts
            ->whereIn('status', self::SUBMITTED_STATUSES)
            ->sortByDesc('last_attempted_at')
            ->first();

        return [
            // FE cần id để mở /tests/{id}/attempt/{attemptId} (Tiếp tục) hoặc .../result/{attemptId}.
            'id' => $current->id,
            'last_result_id' => $lastResult?->id,
            'status' => $current->status,
            'bucket' => self::BUCKET_OF_STATUS[$current->status],
            'best_score' => $bestScore !== null ? (float) $bestScore : null,
            'attempt_count' => (int) $attempts->max('attempt_count'),
            'last_attempted_at' => $attempts->max('last_attempted_at'),
            'answered_count' => $inProgress ? (int) $current->answered_count : null,
            'question_count' => $inProgress ? (int) $current->question_count : null,
        ];
    }
}
