<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SyncSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sessions' => ['present', 'array'],
            'sessions.*.id' => ['nullable', 'integer'],
            'sessions.*.title' => ['required', 'string', 'max:120'],
            'sessions.*.order' => ['nullable', 'integer'],
            'sessions.*.is_visible' => ['boolean'],
            'deleted_ids' => ['array'],
            'deleted_ids.*' => ['integer'],
            'force_delete_ids' => ['array'],
            'force_delete_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sessions.*.title.required' => 'Tên tiến trình không được trống.',
            'sessions.*.title.max' => 'Tên tiến trình tối đa 120 ký tự.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $seen = [];
            foreach ((array) $this->input('sessions', []) as $i => $s) {
                $title = mb_strtolower(trim((string) ($s['title'] ?? '')));
                if ($title === '') {
                    continue;
                }
                if (isset($seen[$title])) {
                    $v->errors()->add("sessions.{$i}.title", 'Tên tiến trình bị trùng trong lớp.');
                } else {
                    $seen[$title] = $i;
                }
            }
        });
    }
}
