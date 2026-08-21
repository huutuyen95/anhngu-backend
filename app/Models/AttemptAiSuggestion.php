<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đề xuất chấm điểm của AI cho MỘT câu trong MỘT lượt làm bài.
 *
 * Đây KHÔNG phải điểm chính thức: điểm thật nằm ở `attempt_answers.score` và chỉ được ghi
 * khi cô bấm Lưu ở màn chấm. Học viên không bao giờ đọc bảng này.
 */
class AttemptAiSuggestion extends Model
{
    protected $fillable = [
        'test_attempt_id',
        'question_id',
        'score',
        'feedback',
        'criteria',
        'provider',
        'model',
        'raw_response',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'criteria' => 'array',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TestAttempt::class, 'test_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
