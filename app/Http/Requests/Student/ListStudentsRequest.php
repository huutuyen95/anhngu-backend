<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:255'], 'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'], 'is_active' => ['nullable', 'boolean'], 'trashed' => ['nullable', 'boolean'], 'sort' => ['nullable', Rule::in(['name', 'email', 'created_at'])], 'dir' => ['nullable', Rule::in(['asc', 'desc'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
