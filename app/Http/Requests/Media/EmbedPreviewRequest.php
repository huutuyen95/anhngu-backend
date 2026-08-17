<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class EmbedPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['url' => ['required', 'string', 'max:2048']];
    }
}
