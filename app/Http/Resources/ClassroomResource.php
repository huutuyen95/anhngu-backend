<?php

namespace App\Http\Resources;

use App\Models\Classroom;
use App\Services\ClassroomStatsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Classroom
 */
class ClassroomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stats = app(ClassroomStatsService::class)->forClass($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'cover_url' => $this->cover_url,
            'status' => $this->status(),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'description' => $this->description,
            'students_count' => $stats['students_count'] ?? 0,
            'sessions_count' => $stats['sessions_count'] ?? 0,
            'open_missions_count' => $stats['open_missions_count'] ?? 0,
            'pending_review_count' => $stats['pending_review_count'] ?? 0,
            'progress_pct' => $stats['progress_pct'] ?? 0,
            'avg_score' => $stats['avg_score'] ?? 0,
            'last_session' => $stats['last_session'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
