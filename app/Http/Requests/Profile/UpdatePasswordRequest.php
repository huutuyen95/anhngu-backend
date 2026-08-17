<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', 'confirmed']];
    }

    public function messages(): array
    {
        return ['password.min' => 'Mật khẩu mới cần ít nhất 8 ký tự', 'password.regex' => 'Mật khẩu cần có cả chữ và số', 'password.confirmed' => 'Hai mật khẩu chưa giống nhau'];
    }
}
