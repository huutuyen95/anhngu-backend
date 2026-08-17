<?php

namespace App\Http\Requests\Test;

use Illuminate\Foundation\Http\FormRequest;

class MoveTestCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['category_id' => ['nullable', 'integer', 'exists:test_categories,id']];
    }
}
