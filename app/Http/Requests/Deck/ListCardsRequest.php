<?php

namespace App\Http\Requests\Deck;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:255'], 'missing' => ['nullable', Rule::in(['audio', 'image', 'ipa', 'example'])]];
    }
}
