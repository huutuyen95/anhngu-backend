<?php

namespace App\Http\Requests\Deck;

use Illuminate\Foundation\Http\FormRequest;

class SyncDeckCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categories' => ['present', 'array'],
            'categories.*.id' => ['nullable', 'integer', 'exists:deck_categories,id'],
            'categories.*.name' => ['required', 'string', 'max:100'],
            'categories.*.order' => ['nullable', 'integer', 'min:0'],
            'deleted_ids' => ['array'],
            'deleted_ids.*' => ['integer', 'exists:deck_categories,id'],
        ];
    }
}
