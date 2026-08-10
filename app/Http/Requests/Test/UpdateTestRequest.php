<?php

namespace App\Http\Requests\Test;

use App\Enums\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Quyền đã chặn ở middleware role:teacher,admin.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'integer', 'exists:test_categories,id'],
            'skill' => ['sometimes', Rule::enum(Skill::class)],
            'is_combo' => ['sometimes', 'boolean'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1'],
            'total_score' => ['sometimes', 'numeric', 'min:0'],
            'scoring_method' => ['sometimes', 'string', 'max:50'],
            'shuffle_questions' => ['sometimes', 'boolean'],
            'word_limit' => ['nullable', 'integer', 'min:1'],
            'rubric' => ['nullable', 'string'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
