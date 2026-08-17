<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class SyncDocumentCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categories' => ['present', 'array'],
            'categories.*.id' => ['nullable', 'integer', 'exists:document_categories,id'],
            'categories.*.name' => ['required', 'string', 'max:100'],
            'categories.*.order' => ['nullable', 'integer'],
            'deleted_ids' => ['array'],
            'deleted_ids.*' => ['integer', 'exists:document_categories,id'],
        ];
    }
}
