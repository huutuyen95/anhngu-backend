<?php

namespace App\Http\Requests\Test;

use Illuminate\Foundation\Http\FormRequest;

class SyncTestCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'categories' => ['present', 'array'],
            'categories.*.id' => ['nullable', 'integer', 'exists:test_categories,id'],
            'categories.*.name' => ['required', 'string', 'max:120'],
            'categories.*.parent_id' => ['nullable', 'integer', 'exists:test_categories,id'],
            'categories.*.order' => ['nullable', 'integer'],
            'deleted_ids' => ['array'],
            'deleted_ids.*' => ['integer', 'exists:test_categories,id'],
        ];
    }
}
