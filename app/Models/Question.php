<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_section_id',
        'order',
        'type',
        'content',
        'audio_url',
        'images',
        'record_limit_seconds',
        'explanation',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'score' => 'decimal:2',
            'images' => 'array',
        ];
    }

    /**
     * Đếm số câu của nhiều đề trong 1 query — tránh N+1 khi dựng danh sách đề.
     *
     * @param  Collection<int, int>  $testIds
     * @return Collection<int, int> keyed theo test_id
     */
    public static function countsByTest(Collection $testIds): Collection
    {
        if ($testIds->isEmpty()) {
            return collect();
        }

        return static::query()
            ->join('test_sections', 'questions.test_section_id', '=', 'test_sections.id')
            ->join('test_parts', 'test_sections.test_part_id', '=', 'test_parts.id')
            ->whereIn('test_parts.test_id', $testIds)
            ->selectRaw('test_parts.test_id as test_id, count(*) as question_count')
            ->groupBy('test_parts.test_id')
            ->pluck('question_count', 'test_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(TestSection::class, 'test_section_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
