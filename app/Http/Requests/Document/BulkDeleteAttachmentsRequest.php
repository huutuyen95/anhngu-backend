<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'exists:document_attachments,id']];
    }
}
