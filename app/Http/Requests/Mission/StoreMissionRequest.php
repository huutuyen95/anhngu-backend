<?php

namespace App\Http\Requests\Mission;

use App\Services\StudentMissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Danh sách loại nội dung khai ở service — thêm loại mới không phải sửa đây.
            'type' => ['required', Rule::in(array_keys(StudentMissionService::SUPPORTED))],
            'id' => ['required', 'integer'],
        ];
    }
}
