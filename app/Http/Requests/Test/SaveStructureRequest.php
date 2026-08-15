<?php

namespace App\Http\Requests\Test;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Lưu toàn bộ cây Part → Section → Question → Option của 1 đề trong 1 request.
 * Đồng bộ: item có `id` thuộc đúng test/part/section/question hiện tại → cập nhật,
 * không có `id` (hoặc `id` không thuộc về cha đó) → tạo mới. Item không được gửi lên → xoá.
 */
class SaveStructureRequest extends FormRequest
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
            'parts' => ['present', 'array'],
            'parts.*.id' => ['sometimes', 'integer'],
            'parts.*.order' => ['required', 'integer', 'min:0'],
            'parts.*.title' => ['required', 'string', 'max:255'],
            'parts.*.display_mode' => ['sometimes', Rule::in(['default', 'image_drag'])],
            'parts.*.image_url' => ['nullable', 'string', 'max:2048'],

            'parts.*.sections' => ['present', 'array'],
            'parts.*.sections.*.id' => ['sometimes', 'integer'],
            'parts.*.sections.*.order' => ['required', 'integer', 'min:0'],
            'parts.*.sections.*.instruction' => ['nullable', 'string'],
            'parts.*.sections.*.passage' => ['nullable', 'string'],
            'parts.*.sections.*.audio_url' => ['nullable', 'string', 'max:2048'],
            'parts.*.sections.*.max_plays' => ['nullable', 'integer', 'min:1'],

            'parts.*.sections.*.questions' => ['present', 'array'],
            'parts.*.sections.*.questions.*.id' => ['sometimes', 'integer'],
            'parts.*.sections.*.questions.*.order' => ['required', 'integer', 'min:0'],
            'parts.*.sections.*.questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'parts.*.sections.*.questions.*.content' => ['nullable', 'string'],
            // Gợi ý cho học viên (câu nói: "You should say…") — hiện lúc ĐANG làm bài,
            // khác `explanation` là lời giải chỉ lộ sau khi nộp.
            'parts.*.sections.*.questions.*.hint' => ['nullable', 'string', 'max:2000'],
            'parts.*.sections.*.questions.*.audio_url' => ['nullable', 'string', 'max:2048'],
            'parts.*.sections.*.questions.*.images' => ['nullable', 'array'],
            'parts.*.sections.*.questions.*.images.*' => ['string', 'url', 'max:2048'],
            'parts.*.sections.*.questions.*.record_limit_seconds' => ['nullable', 'integer', 'min:5', 'max:600'],
            'parts.*.sections.*.questions.*.explanation' => ['nullable', 'string'],
            'parts.*.sections.*.questions.*.score' => ['sometimes', 'numeric', 'min:0'],

            'parts.*.sections.*.questions.*.options' => ['sometimes', 'array'],
            'parts.*.sections.*.questions.*.options.*.id' => ['sometimes', 'integer'],
            'parts.*.sections.*.questions.*.options.*.label' => ['nullable', 'string', 'max:4'],
            'parts.*.sections.*.questions.*.options.*.content' => ['required', 'string'],
            'parts.*.sections.*.questions.*.options.*.is_correct' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('parts', []) as $pi => $part) {
                foreach ($part['sections'] ?? [] as $si => $section) {
                    foreach ($section['questions'] ?? [] as $qi => $question) {
                        $type = $question['type'] ?? null;
                        $options = $question['options'] ?? [];
                        $path = "parts.{$pi}.sections.{$si}.questions.{$qi}.options";

                        if (in_array($type, [QuestionType::Writing->value, QuestionType::Speaking->value], true)) {
                            continue;
                        }

                        if (count($options) < 1) {
                            $validator->errors()->add($path, 'Câu hỏi cần ít nhất 1 lựa chọn.');

                            continue;
                        }

                        $hasCorrect = collect($options)->contains(fn ($o) => (bool) ($o['is_correct'] ?? false));
                        if (! $hasCorrect) {
                            $validator->errors()->add($path, 'Câu hỏi cần ít nhất 1 đáp án đúng.');
                        }
                    }
                }
            }
        });
    }
}
