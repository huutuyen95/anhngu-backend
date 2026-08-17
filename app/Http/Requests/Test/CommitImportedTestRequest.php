<?php

namespace App\Http\Requests\Test;

use Illuminate\Foundation\Http\FormRequest;

class CommitImportedTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'skill' => ['required', 'string'], 'category_id' => ['nullable', 'integer', 'exists:test_categories,id'], 'parts' => ['required', 'array', 'min:1']];
    }
}
