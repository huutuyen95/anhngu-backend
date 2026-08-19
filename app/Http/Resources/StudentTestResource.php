<?php

namespace App\Http\Resources;

use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Một đề trên card ở "Thư viện → Đề thi" (khu học viên).
 *
 * KHÔNG lộ cấu hình đề (is_published / rubric / scoring_method / shuffle_questions...) —
 * những thứ đó chỉ có ở TestResource + TestDetailResource(forTeacher: true) của khu giáo viên.
 *
 * `question_count` và `attempt_summary` do StudentTestService gắn vào model.
 *
 * @mixin Test
 */
class StudentTestResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $this->slug,
            'skill' => $this->skill->value,
            'duration_minutes' => $this->duration_minutes,
            'total_score' => (float) $this->total_score,
            'word_limit' => $this->word_limit,
            'question_count' => (int) $this->question_count,
            // Chỉ số tổng hợp của cả lớp — đếm trên activity_logs (xem StudentTestService).
            'attempts_total' => (int) $this->attempts_total,
            'avg_score' => $this->avg_score !== null ? round((float) $this->avg_score, 1) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'category' => $this->categoryPayload(),
            'attempt' => $this->attempt_summary,
        ];
    }

    /**
     * Nhãn nhóm của đề. `path` ghép sẵn "<lớp> / <thư mục cha> / <thư mục>" (bỏ phần nào null),
     * vd "6A1 / Unit 5". `classroom_name` null = thư mục dùng chung, không gắn lớp nào —
     * FE tự quyết nhãn hiển thị cho trường hợp này.
     *
     * @return array<string, mixed>|null
     */
    private function categoryPayload(): ?array
    {
        $category = $this->category;

        if (! $category) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'parent_name' => $category->parent?->name,
            'classroom_name' => $category->classroom?->name,
            'path' => collect([
                $category->classroom?->name,
                $category->parent?->name,
                $category->name,
            ])->filter()->implode(' / '),
        ];
    }
}
