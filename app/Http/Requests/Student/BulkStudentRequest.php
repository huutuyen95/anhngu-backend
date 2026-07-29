<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStudentRequest extends FormRequest
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
            'action' => ['required', Rule::in(['lock', 'unlock', 'delete', 'assign_class'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
            'classroom_id' => ['required_if:action,assign_class', 'nullable', 'integer', 'exists:classrooms,id'],
            'mode' => ['required_if:action,assign_class', 'nullable', Rule::in(['add', 'move'])],
        ];
    }
}
