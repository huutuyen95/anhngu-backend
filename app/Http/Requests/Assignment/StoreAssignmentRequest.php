<?php

namespace App\Http\Requests\Assignment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['classroom_id' => ['required', 'integer', 'exists:classrooms,id'], 'class_session_id' => ['required', 'integer', 'exists:class_sessions,id'], 'items' => ['required', 'array', 'min:1'], 'items.*.type' => ['required', 'string', Rule::in(['test', 'writing', 'deck', 'document', 'lecture'])], 'items.*.id' => ['required', 'integer'], 'student_ids' => ['nullable', 'array'], 'student_ids.*' => ['integer', 'exists:users,id'], 'due_date' => ['nullable', 'date'], 'attempts_allowed' => ['nullable', 'integer', 'min:1'], 'schedule' => ['required', Rule::in(['now', 'at', 'draft'])], 'scheduled_at' => ['nullable', 'date', 'after:now', 'required_if:schedule,at'], 'notify' => ['sometimes', 'boolean']];
    }

    public function messages(): array
    {
        return ['scheduled_at.after' => 'Thời điểm lên lịch phải ở tương lai.'];
    }
}
