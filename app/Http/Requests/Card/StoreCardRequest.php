<?php

namespace App\Http\Requests\Card;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'term' => ['required', 'string', 'max:255'],
            'meaning' => ['required', 'string', 'max:255'],
            'pos' => ['nullable', 'string', 'max:12'],
            'ipa' => ['nullable', 'string', 'max:80'],
            'example' => ['nullable', 'string'],
        ];
    }
}
