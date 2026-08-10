<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        ];
    }

    /**
     * Hạn nộp = started_at + thời lượng đề. `null` nếu đề không giới hạn thời gian
     * (duration_minutes = 0). Cần quan hệ `test` đã nạp.
     */
    public function deadlineAt(): ?\Illuminate\Support\Carbon
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
