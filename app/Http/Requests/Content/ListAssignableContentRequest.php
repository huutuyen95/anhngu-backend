<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAssignableContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(['test', 'writing', 'deck', 'document', 'lecture'])],
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }
}
