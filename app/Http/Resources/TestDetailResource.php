<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trả cấu trúc đề (parts → sections → questions → options).
 * $revealAnswers = false khi làm bài (ẩn is_correct + explanation),
 * = true khi xem kết quả (lộ đáp án đúng + lời giải).
 */
class TestDetailResource extends JsonResource
{
    public static $wrap = null;

    public function __construct($resource, protected bool $revealAnswers = false)
    {
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
            'parts' => $this->parts->map(fn ($part) => [
                'id' => $part->id,
                'title' => $part->title,
                'order' => $part->order,
                'sections' => $part->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'instruction' => $section->instruction,
                    'passage' => $section->passage,
                    'audio_url' => $section->audio_url,
                    'order' => $section->order,
                    'questions' => $section->questions->map(fn ($question) => [
                        'id' => $question->id,
                        'order' => $question->order,
                        'type' => $question->type->value,
                        'content' => $question->content,
                        'audio_url' => $question->audio_url,
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
