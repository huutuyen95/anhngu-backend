<?php

namespace App\Http\Requests\Deck;

use Illuminate\Foundation\Http\FormRequest;

class ListDecksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'category_id' => ['nullable', 'integer', 'exists:deck_categories,id'],
            'is_published' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
