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
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
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
