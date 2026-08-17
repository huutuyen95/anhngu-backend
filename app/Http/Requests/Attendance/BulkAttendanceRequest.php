<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['items' => ['required', 'array'], 'items.*.user_id' => ['required', 'integer', 'exists:users,id'], 'items.*.status' => ['required', Rule::in(['on_time', 'late', 'absent'])], 'items.*.comment' => ['nullable', 'string', 'max:500']];
    }
}
