<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class AttachStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['user_ids' => ['required', 'array', 'min:1'], 'user_ids.*' => ['integer', 'exists:users,id']];
    }
}
