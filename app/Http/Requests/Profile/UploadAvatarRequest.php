<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['avatar' => ['required', 'image', 'mimes:jpeg,png', 'max:2048']];
    }

    public function messages(): array
    {
        return ['avatar.max' => 'Ảnh nặng quá 2MB, em chọn ảnh khác nhé.', 'avatar.mimes' => 'Chỉ nhận ảnh JPG hoặc PNG.', 'avatar.image' => 'Chỉ nhận ảnh JPG hoặc PNG.'];
    }
}
