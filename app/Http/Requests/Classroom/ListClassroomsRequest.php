<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListClassroomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(['upcoming', 'ended', 'active'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
