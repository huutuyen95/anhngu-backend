<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TestAttempt extends Model
{
    use HasFactory;

    /** Số lần được phép rời tab; vượt quá (thoát lần thứ LIMIT+1) → tự nộp bài. */
    public const TAB_EXIT_LIMIT = 3;

    protected $fillable = [
        'user_id',
        'test_id',
        'classroom_id',
        'mission_id',
        'source',
        'attempt_category',
        'started_at',
        'submitted_at',
        'total_score',
        'correct_count',
        'question_count',
        'tab_exit_count',
        'remaining_seconds',
        'resumed_at',
        'config_snapshot',
        'attempt_count',
        'last_attempted_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'resumed_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'total_score' => 'decimal:2',
            'config_snapshot' => 'array',
        ];
    }

    /** Lấy 1 giá trị trong snapshot cấu hình lúc bắt đầu, fallback về cấu hình hiện hành. */
    public function configValue(string $key, mixed $default = null): mixed
    {
        $snapshot = $this->config_snapshot ?? [];

        return $snapshot[$key] ?? setting($key, $default);
    }

    /** Đề có giới hạn giờ hay không. Cần quan hệ `test` đã nạp. */
    public function isTimed(): bool
    {
        return (int) $this->test->duration_minutes > 0;
    }

    /**
     * Đồng hồ đang chạy (học viên đang ở trong màn làm bài).
     * Lượt cũ chưa có `remaining_seconds` được coi như đang chạy — nó vẫn tính theo
     * mốc bắt đầu cho tới lần tạm dừng đầu tiên.
     */
    public function clockRunning(): bool
    {
        return $this->remaining_seconds === null || $this->resumed_at !== null;
    }

    /**
     * Hạn nộp khi đồng hồ ĐANG CHẠY = resumed_at + số giây còn lại.
     * `null` khi đề không giới hạn giờ HOẶC đồng hồ đang tạm dừng (học viên đã rời màn
     * làm bài) — lúc đó dùng `remainingSeconds()` để biết còn bao nhiêu.
     */
    public function deadlineAt(): ?Carbon
    {
        if (! $this->started_at || ! $this->isTimed()) {
            return null;
        }

        // Phòng lượt cũ chưa kịp backfill: vẫn tính theo mốc bắt đầu như trước.
        if ($this->remaining_seconds === null) {
            return $this->started_at->clone()->addMinutes((int) $this->test->duration_minutes);
        }

        if (! $this->clockRunning()) {
            return null;
        }

        return $this->resumed_at->clone()->addSeconds((int) $this->remaining_seconds);
    }

    /** Số giây còn lại ngay lúc này; `null` nếu đề không giới hạn giờ. */
    public function remainingSeconds(): ?int
    {
        if (! $this->isTimed()) {
            return null;
        }

        $deadline = $this->deadlineAt();

        if ($deadline) {
            return max(0, $deadline->getTimestamp() - now()->getTimestamp());
        }

        return max(0, (int) ($this->remaining_seconds ?? 0));
    }

    /**
     * Dừng đồng hồ, chốt lại số giây còn lại. Gọi khi học viên rời màn làm bài.
     * Gọi nhiều lần liên tiếp là an toàn (lần sau không trừ thêm).
     */
    public function pauseClock(): void
    {
        if (! $this->isTimed() || ! $this->clockRunning()) {
            return;
        }

        $this->forceFill([
            'remaining_seconds' => $this->remainingSeconds(),
            'resumed_at' => null,
        ])->save();
    }

    /** Chạy lại đồng hồ từ số giây còn lại. Gọi khi học viên quay vào làm tiếp. */
    public function resumeClock(): void
    {
        if (! $this->isTimed() || $this->clockRunning()) {
            return;
        }

        $this->forceFill([
            // Lượt cũ chưa có số giây còn lại → chốt theo cách tính cũ ngay lần đầu.
            'remaining_seconds' => $this->remainingSeconds(),
            'resumed_at' => now(),
        ])->save();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /**
     * Lượt của bài ĐƯỢC GIAO trong lớp. Mọi số liệu báo cáo lớp phải đi qua đây — lượt tự
     * luyện ở Thư viện (`mission_id` null) không được tính vào tiến trình/điểm của lớp.
     *
     * @param  Builder<TestAttempt>  $query
     */
    public function scopeAssigned($query): void
    {
        $query->whereNotNull('mission_id');
    }

    /**
     * Lượt đã chốt điểm: tự chấm xong (`submitted`) hoặc cô đã chấm tay (`graded`).
     * `pending_review` mới có điểm tạm của phần tự chấm nên bị loại.
     *
     * @param  Builder<TestAttempt>  $query
     */
    public function scopeScored($query): void
    {
        $query->whereIn('status', ['submitted', 'graded'])->whereNotNull('total_score');
    }

    /**
     * Giới hạn truy vấn về đúng "nguồn" của lượt `$attempt`: bài được giao thì chỉ đụng tới
     * các lượt cùng mission, tự luyện thì chỉ đụng tới các lượt tự luyện khác. Dùng cho dedup
     * lượt-điểm-cao-nhất — chỗ trước đây gộp chung cả hai nguồn và xoá chéo dữ liệu.
     *
     * @param  Builder<TestAttempt>  $query
     */
    public function scopeSameScope($query, self $attempt): void
    {
        $query->where('user_id', $attempt->user_id)
            ->where('test_id', $attempt->test_id)
            ->when(
                $attempt->mission_id,
                fn ($q) => $q->where('mission_id', $attempt->mission_id),
                fn ($q) => $q->whereNull('mission_id'),
            );
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function skillScores(): HasMany
    {
        return $this->hasMany(AttemptSkillScore::class);
    }
}
