<?php

namespace App\Http\Requests\Setting;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'values' => ['required', 'array', 'min:1'],
        ];
    }

    protected function passedValidation(): void
    {
        $settings = app(SettingService::class);
        $clean = $settings->filterWritableValues($this->input('values', []));
        $rules = [];
        $attributes = [];

        foreach ($clean as $key => $value) {
            $meta = $settings->field($key);
            $rules[$key] = $this->rulesFor($meta);
            $attributes[$key] = $meta['label'] ?? $key;
        }

        Validator::make($clean, $rules, [], $attributes)->validate();
    }

    private function rulesFor(array $meta): array
    {
        $rules = $meta['rules'] ?? [];
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        $base = match ($meta['type']) {
            'int' => ['integer'],
            'float' => ['numeric'],
            'bool' => ['boolean'],
            'json' => ['array'],
            default => ['string'],
        };
        array_unshift($base, ! empty($meta['required']) ? 'required' : 'nullable');

        return array_values(array_unique([...$base, ...$rules]));
    }
}
