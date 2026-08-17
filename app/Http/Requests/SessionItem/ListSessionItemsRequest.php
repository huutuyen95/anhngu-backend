<?php

namespace App\Http\Requests\SessionItem;

use Illuminate\Foundation\Http\FormRequest;

class ListSessionItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['session_id' => ['required', 'integer', 'exists:class_sessions,id']];
    }
}
