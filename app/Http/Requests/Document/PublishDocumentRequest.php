<?php

namespace App\Http\Requests\Document;

use Illuminate\Foundation\Http\FormRequest;

class PublishDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['is_published' => ['required', 'boolean']];
    }
}
