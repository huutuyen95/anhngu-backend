<?php

namespace App\Http\Requests\Article;

use Illuminate\Foundation\Http\FormRequest;

class SyncArticleCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categories' => ['present', 'array'],
            'categories.*.id' => ['nullable', 'integer', 'exists:article_categories,id'],
            'categories.*.name' => ['required', 'string', 'max:100'],
            'categories.*.order' => ['nullable', 'integer', 'min:0'],
            'deleted_ids' => ['array'],
            'deleted_ids.*' => ['integer', 'exists:article_categories,id'],
        ];
    }
}
