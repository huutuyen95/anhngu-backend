<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('facebook_url') && ! Str::startsWith($this->input('facebook_url'), ['http://', 'https://'])) {
            $this->merge(['facebook_url' => 'https://'.$this->input('facebook_url')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'regex:/^0[\d\s]{9,}$/'],
            'birthday' => ['nullable', 'date', 'before:today', 'after_or_equal:'.now()->subYears(100)->toDateString()],
            'gender' => ['nullable', Rule::in(['male', 'female', 'unspecified'])],
            'address' => ['nullable', 'string', 'max:255'], 'facebook_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Em nhập họ tên nhé', 'name.max' => 'Họ tên tối đa 100 ký tự',
            'phone.regex' => 'Số điện thoại chưa đúng', 'birthday.before' => 'Em kiểm tra lại ngày sinh nhé',
            'birthday.before_or_equal' => 'Em kiểm tra lại ngày sinh nhé', 'birthday.after_or_equal' => 'Em kiểm tra lại ngày sinh nhé',
            'birthday.date' => 'Em kiểm tra lại ngày sinh nhé', 'facebook_url.url' => 'Link chưa hợp lệ',
        ];
    }
}
