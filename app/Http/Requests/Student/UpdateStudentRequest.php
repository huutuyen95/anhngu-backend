<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
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
        // Email KHÔNG cho đổi (disable ở UI) — bỏ qua email nếu client gửi lên.
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'classroom_ids' => ['sometimes', 'array'],
            'classroom_ids.*' => ['integer', 'exists:classrooms,id'],
        ];
    }
}
