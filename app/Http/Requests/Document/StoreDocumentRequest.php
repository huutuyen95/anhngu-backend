<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['document', 'lecture'])], 'title' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:document_categories,id'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'], 'body' => ['nullable', 'string'],
            'classroom_ids' => ['sometimes', 'array'], 'classroom_ids.*' => ['integer', 'exists:classrooms,id'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
