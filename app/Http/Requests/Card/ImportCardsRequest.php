<?php

namespace App\Http\Requests\Card;

use Illuminate\Foundation\Http\FormRequest;

class ImportCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'dry_run' => ['sometimes', 'boolean'],
            'auto_ipa' => ['sometimes', 'boolean'],
            'overwrite' => ['sometimes', 'boolean'],
        ];
    }
}
