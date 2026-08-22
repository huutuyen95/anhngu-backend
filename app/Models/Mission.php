<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Mission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assigned_by',
        'classroom_id',
        'class_session_id',
        'missionable_type',
        'missionable_id',
        'source',
        'status',
        'due_date',
        'attempts_allowed',
        'scheduled_at',
        'completed_at',
        'deadline_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** Lượt đã nộp của nhiệm vụ — không còn làm tiếp được, tính vào số lần đã dùng. */
    private const USED_ATTEMPT_STATUSES = ['pending_review', 'submitted', 'graded'];

    /**
     * Số lần học viên đã nộp bài cho nhiệm vụ này (đối chiếu với `attempts_allowed`).
     *
     * Cộng `attempt_count` chứ không đếm dòng: dedup lượt-điểm-cao-nhất gộp nhiều lần nộp
     * vào một dòng, còn lượt `pending_review` thì không bị gộp nên nằm ở nhiều dòng.
     */
    public function attemptsUsed(): int
    {
        return (int) TestAttempt::where('mission_id', $this->id)
            ->whereIn('status', self::USED_ATTEMPT_STATUSES)
            ->sum('attempt_count');
    }

    /** Còn lượt để mở bài không. Bài giao mặc định chỉ 1 lượt. */
    public function hasAttemptsLeft(): bool
    {
        return $this->attemptsUsed() < max(1, (int) ($this->attempts_allowed ?? 1));
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function missionable(): MorphTo
    {
        return $this->morphTo();
    }
}
