<?php

namespace App\Http\Requests\Setting;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeleteSettingFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['key' => ['required', 'string']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $meta = app(SettingService::class)->field((string) $this->input('key'));
            if (! $meta || ($meta['type'] ?? null) !== 'file') {
                $validator->errors()->add('key', 'Khoá cấu hình không hợp lệ.');
            }
        }];
    }
}
