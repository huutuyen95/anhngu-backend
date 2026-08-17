<?php

namespace App\Http\Requests\Setting;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UploadSettingFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $meta = app(SettingService::class)->field((string) $this->input('key'));
        $accept = $meta['accept'] ?? 'png,jpg,jpeg,svg';
        $maxKb = $meta['max_kb'] ?? 2048;

        return [
            'key' => ['required', 'string'],
            'file' => ['required', 'file', 'mimes:'.$accept, 'max:'.$maxKb],
        ];
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

    public function attributes(): array
    {
        $meta = app(SettingService::class)->field((string) $this->input('key'));

        return ['file' => $meta['label'] ?? 'tệp'];
    }
}
