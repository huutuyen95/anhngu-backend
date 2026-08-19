<?php

namespace App\Http\Requests\Mission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tab' => ['nullable', Rule::in(['upcoming', 'done'])],
        ];
    }

    /** Tab đang xem, mặc định "7 ngày tới". */
    public function tab(): string
    {
        return $this->validated()['tab'] ?? 'upcoming';
    }
}
