<?php

namespace App\Http\Requests\Test;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['force' => ['sometimes', 'boolean']];
    }
}
