<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trả cấu trúc đề (parts → sections → questions → options).
 * $revealAnswers = false khi làm bài (ẩn is_correct + explanation),
 * = true khi xem kết quả (lộ đáp án đúng + lời giải).
 * $forTeacher = true khi admin sửa đề — lộ thêm cấu hình của đề (is_published/rubric/
 * scoring_method/...) mà học sinh KHÔNG được thấy.
 */
class TestDetailResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(
        $resource,
        protected bool $revealAnswers = false,
        protected bool $forTeacher = false,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'skill' => $this->skill->value,
            'duration_minutes' => $this->duration_minutes,
            'total_score' => (float) $this->total_score,
            'word_limit' => $this->word_limit,
            ...($this->forTeacher ? [
                'is_published' => (bool) $this->is_published,
                'rubric' => $this->rubric,
                'scoring_method' => $this->scoring_method,
                'is_combo' => (bool) $this->is_combo,
                'thumbnail_url' => $this->thumbnail_url,
            ] : []),
            'parts' => $this->parts->map(fn ($part) => [
                'id' => $part->id,
                'title' => $part->title,
                'order' => $part->order,
                'sections' => $part->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'instruction' => $section->instruction,
                    'passage' => $section->passage,
                    'audio_url' => $section->audio_url,
                    'max_plays' => $section->max_plays,
                    'order' => $section->order,
                    'questions' => $section->questions->map(fn ($question) => [
                        'id' => $question->id,
                        'order' => $question->order,
                        'type' => $question->type->value,
                        'content' => $question->content,
                        'audio_url' => $question->audio_url,
                        'images' => $question->images,
                        'record_limit_seconds' => $question->record_limit_seconds,
                        ...($this->revealAnswers ? ['explanation' => $question->explanation] : []),
                        'options' => $question->options->map(fn ($option) => [
                            'id' => $option->id,
                            'label' => $option->label,
                            'content' => $option->content,
                            ...($this->revealAnswers ? ['is_correct' => (bool) $option->is_correct] : []),
                        ])->values(),
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }
}
