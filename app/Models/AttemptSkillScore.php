<?php

namespace App\Models;

use App\Enums\Skill;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptSkillScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_attempt_id',
        'skill',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'skill' => Skill::class,
            'score' => 'decimal:2',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TestAttempt::class, 'test_attempt_id');
    }
}
