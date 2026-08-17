<?php

namespace App\Http\Requests\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'order' => ['nullable', 'integer'], 'note' => ['nullable', 'string'], 'held_on' => ['nullable', 'date']];
    }
}
