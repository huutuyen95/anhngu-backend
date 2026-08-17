<?php

namespace App\Http\Requests\Card;

use Illuminate\Foundation\Http\FormRequest;

class LookupIpaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['words' => ['nullable', 'string', 'max:4000']];
    }
}
