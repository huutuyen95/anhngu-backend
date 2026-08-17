<?php

namespace App\Http\Requests\Deck;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:deck_categories,id'],
            'classroom_ids' => ['sometimes', 'array'],
            'classroom_ids.*' => ['integer', 'exists:classrooms,id'],
            'description' => ['nullable', 'string'],
            'tts_voice' => ['sometimes', Rule::in(['en-GB-female', 'en-GB-male', 'en-US-female', 'en-US-male'])],
            'tts_rate' => ['sometimes', 'numeric', 'between:0.5,1.5'],
            'tts_repeat' => ['sometimes', Rule::in(['1', '2', 'auto'])],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
