<?php

namespace App\Http\Requests\Test;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminTestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:255'], 'skill' => ['nullable', 'string', 'max:30'], 'format' => ['nullable', 'string', 'in:standard,ielts_simulation'], 'category_id' => ['nullable', 'integer'], 'is_published' => ['nullable', 'boolean'], 'sort' => ['nullable', Rule::in(['title', 'created_at', 'updated_at'])], 'dir' => ['nullable', Rule::in(['asc', 'desc'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
