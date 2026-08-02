<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            // Email unique KỂ CẢ bản ghi đã xoá mềm (rule unique query cả bảng, không bỏ soft-deleted).
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string'],
            'avatar_url' => ['nullable', 'string', 'max:2048'],
            'classroom_ids' => ['sometimes', 'array'],
            'classroom_ids.*' => ['integer', 'exists:classrooms,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email này đã được dùng cho một tài khoản khác.',
        ];
    }
}
