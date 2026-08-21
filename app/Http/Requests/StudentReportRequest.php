<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['nullable', 'in:overview,class'],
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'period' => ['nullable', 'in:7d,30d,90d'],
        ];
    }
}
