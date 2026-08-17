<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class ResetSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group' => ['nullable', 'string', 'required_without:keys'],
            'keys' => ['nullable', 'array', 'min:1', 'required_without:group'],
            'keys.*' => ['string'],
        ];
    }
}
