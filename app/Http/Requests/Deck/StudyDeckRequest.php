<?php

namespace App\Http\Requests\Deck;

use Illuminate\Foundation\Http\FormRequest;

class StudyDeckRequest extends FormRequest
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
