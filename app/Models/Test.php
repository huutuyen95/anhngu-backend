<?php

namespace App\Models;

use App\Enums\Skill;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Test extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'category_id',
        'title',
        'description',
        'slug',
        'skill',
        'is_combo',
        'thumbnail_url',
        'duration_minutes',
        'total_score',
        'scoring_method',
        'shuffle_questions',
        'word_limit',
        'rubric',
        'ai_grading',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'skill' => Skill::class,
            'is_combo' => 'boolean',
            'ai_grading' => 'boolean',
            'shuffle_questions' => 'boolean',
            'is_published' => 'boolean',
            'total_score' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TestCategory::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(TestPart::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    public function questionCount(): int
    {
        return Question::whereHas('section.part', fn ($query) => $query->where('test_id', $this->id))->count();
    }
}
