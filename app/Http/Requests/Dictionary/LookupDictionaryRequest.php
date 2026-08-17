<?php

namespace App\Http\Requests\Dictionary;

use Illuminate\Foundation\Http\FormRequest;

class LookupDictionaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['word' => ['nullable', 'string', 'max:80']];
    }
}
