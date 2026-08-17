<?php

namespace App\Http\Requests\Test;

use Illuminate\Foundation\Http\FormRequest;

class ImportTestDryRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:10240', 'mimes:docx,doc,zip']];
    }
}
