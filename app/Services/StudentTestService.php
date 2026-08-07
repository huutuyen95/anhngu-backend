<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
        $query = $this->baseQuery($filters)
            // Nhãn nhóm trên card: "<lớp> / <thư mục cha> / <thư mục>".
            ->with([
                'category:id,name,parent_id,classroom_id',
                'category.parent:id,name',
                'category.classroom:id,name',
            ])
            // Điểm TB / tổng lượt làm: đếm trên activity_logs vì test_attempts đã bị dedup xoá.
            ->withCount(['activityLogs as attempts_total' => fn ($q) => $q->where('type', 'test_attempt')])
            ->withAvg(['activityLogs as avg_score' => fn ($q) => $q->where('type', 'test_attempt')], 'score');

        $this->applyStatusFilter($query, $filters, $student);
        $this->applySort($query, $filters);

        $page = $query->paginate($this->perPage($filters))->withQueryString();

        $tests = $page->getCollection();
        $questionCounts = Question::countsByTest($tests->pluck('id'));
        $attemptsByTest = $this->attemptsOf($student, $tests->pluck('id'));

        $tests->each(function (Test $test) use ($questionCounts, $attemptsByTest) {
            $attempts = $attemptsByTest->get($test->id);

            $test->setAttribute('question_count', (int) ($questionCounts[$test->id] ?? 0));
            $test->setAttribute('attempt_summary', $attempts ? $this->attemptSummary($attempts) : null);
        });

        return $page;
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
        $testIds = $this->baseQuery($filters)->pluck('id');

        $counts = array_fill_keys(self::BUCKETS, 0);

        foreach ($this->attemptsOf($student, $testIds) as $attempts) {
            $counts[$this->bucketOf($attempts)]++;
        }

        // Đề chưa có lượt nào của học viên này = "Chưa làm".
        $counts['todo'] = $testIds->count() - ($counts['doing'] + $counts['done'] + $counts['grading']);

        return [
            'new_this_week' => (clone $this->baseQuery($filters))
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
            'status_counts' => $counts,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Test>
     */
    private function baseQuery(array $filters): Builder
    {
        return Test::query()
            ->where('is_published', true)
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('title', 'like', '%'.$term.'%'))
            ->when($filters['skill'] ?? null, fn ($q, $skill) => $q->where('skill', $skill));
    }

    /**
     * Lọc theo nhóm trạng thái. Mỗi đề chỉ thuộc ĐÚNG 1 nhóm (giống card ở FE): đang làm dở thì
     * là `doing` kể cả khi đã có lượt nộp cũ, nên `grading`/`done` phải loại lượt in_progress ra.
     *
     * @param  Builder<Test>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyStatusFilter(Builder $query, array $filters, User $student): void
    {
        $buckets = $this->requestedBuckets($filters);

        if ($buckets->isEmpty()) {
            return;
        }

        $query->where(function (Builder $query) use ($buckets, $student) {
            foreach ($buckets as $bucket) {
                $query->orWhere(fn (Builder $q) => $this->whereBucket($q, $bucket, $student));
            }
        });
    }

    /**
     * @param  Builder<Test>  $query
     */
    private function whereBucket(Builder $query, string $bucket, User $student): void
    {
        $mine = fn (array $statuses) => fn ($q) => $q
            ->where('user_id', $student->id)
            ->whereIn('status', $statuses);

        match ($bucket) {
            'todo' => $query->whereDoesntHave('attempts', fn ($q) => $q->where('user_id', $student->id)),
            'doing' => $query->whereHas('attempts', $mine(['in_progress'])),
            'grading' => $query->whereHas('attempts', $mine(['pending_review']))
                ->whereDoesntHave('attempts', $mine(['in_progress'])),
            'done' => $query->whereHas('attempts', $mine(self::FINALIZED_STATUSES))
                ->whereDoesntHave('attempts', $mine(['in_progress', 'pending_review'])),
        };
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
     * @param  Builder<Test>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $sort = in_array($filters['sort'] ?? '', self::SORTS, true) ? $filters['sort'] : 'newest';

        match ($sort) {
            'name' => $query->orderBy('title'),
            'popular' => $query->orderByDesc('attempts_total'),
            default => $query->orderByDesc('created_at'),
        };

        // Chốt thứ tự để phân trang không nhảy khi trùng giá trị sort.
        $query->orderByDesc('id');
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

        return TestAttempt::query()
            ->where('user_id', $student->id)
            ->whereIn('status', array_keys(self::BUCKET_OF_STATUS))
            ->whereIn('test_id', $testIds)
            // Tiến độ "câu 12/40" của lượt đang làm dở — chỉ đếm câu đã thực sự trả lời.
            ->withCount(['answers as answered_count' => fn ($q) => $q->where(
                fn ($q) => $q->whereNotNull('question_option_id')
                    ->orWhere(fn ($q) => $q->whereNotNull('answer_text')->where('answer_text', '!=', ''))
                    ->orWhereNotNull('answer_file_url')
            )])
            ->get()
            ->groupBy('test_id');
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

        return [
            // FE cần id để mở /tests/{id}/attempt/{attemptId} (Tiếp tục) hoặc .../result/{attemptId}.
            'id' => $current->id,
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
