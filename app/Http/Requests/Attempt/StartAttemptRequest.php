<?php

namespace App\Http\Requests\Attempt;

use Illuminate\Foundation\Http\FormRequest;

class StartAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['mission_id' => ['nullable', 'integer', 'exists:missions,id']];
    }
}
