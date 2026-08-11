<?php

namespace App\Models;

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
        'attempt_category',
        'started_at',
        'submitted_at',
        'total_score',
        'correct_count',
        'question_count',
        'tab_exit_count',
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

    /**
     * Hạn nộp = started_at + thời lượng đề. `null` nếu đề không giới hạn thời gian
     * (duration_minutes = 0). Cần quan hệ `test` đã nạp.
     */
    public function deadlineAt(): ?Carbon
    {
        if (! $this->started_at) {
            return null;
        }

        $duration = (int) $this->test->duration_minutes;

        return $duration > 0 ? $this->started_at->clone()->addMinutes($duration) : null;
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

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function skillScores(): HasMany
    {
        return $this->hasMany(AttemptSkillScore::class);
    }
}
