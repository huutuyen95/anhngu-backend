<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Đề thi ở dạng rút gọn (danh sách / tạo / sửa) — không kèm cây cấu trúc.
 *
 * @mixin \App\Models\Test
 */
class TestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'skill' => $this->skill->value,
            'is_combo' => (bool) $this->is_combo,
            'thumbnail_url' => $this->thumbnail_url,
            'duration_minutes' => $this->duration_minutes,
            'total_score' => (float) $this->total_score,
            'scoring_method' => $this->scoring_method,
            'word_limit' => $this->word_limit,
            'rubric' => $this->rubric,
            'is_published' => (bool) $this->is_published,
            'question_count' => $this->when($this->question_count !== null, fn () => (int) $this->question_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
