<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'in:all,unread'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
