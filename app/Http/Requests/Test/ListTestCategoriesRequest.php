<?php

namespace App\Http\Requests\Test;

use Illuminate\Foundation\Http\FormRequest;

class ListTestCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['classroom_id' => ['nullable', 'integer', 'exists:classrooms,id']];
    }
}
