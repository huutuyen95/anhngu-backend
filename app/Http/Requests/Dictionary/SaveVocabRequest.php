<?php

namespace App\Http\Requests\Dictionary;

use Illuminate\Foundation\Http\FormRequest;

class SaveVocabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['word' => ['required', 'string', 'max:80'], 'meaning' => ['nullable', 'string', 'max:255'], 'ipa' => ['nullable', 'string', 'max:120']];
    }
}
