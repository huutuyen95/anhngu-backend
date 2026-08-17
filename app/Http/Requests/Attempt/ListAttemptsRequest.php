<?php

namespace App\Http\Requests\Attempt;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAttemptsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['in_progress', 'pending_review', 'submitted', 'graded'])],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'test_id' => ['nullable', 'integer', 'exists:tests,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'source' => ['nullable', Rule::in(['assignment', 'library'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
