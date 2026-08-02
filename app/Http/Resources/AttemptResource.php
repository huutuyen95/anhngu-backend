<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dòng danh sách "Kết quả làm bài" / "Chờ chấm" cho giáo viên.
 *
 * @mixin \App\Models\TestAttempt
 */
class AttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'test' => $this->whenLoaded('test', fn () => [
                'id' => $this->test->id,
                'title' => $this->test->title,
                'skill' => $this->test->skill->value,
            ]),
            'student' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'total_score' => $this->total_score !== null ? (float) $this->total_score : null,
            'correct_count' => $this->correct_count,
            'question_count' => $this->question_count,
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];
    }
}
