<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type', 'image');

        return [
            'type' => ['sometimes', Rule::in(['image', 'audio'])],
            'file' => $type === 'audio'
                ? ['required', 'file', 'mimes:mp3,m4a,mp4,wav,ogg,oga,aac,webm,3gp,3gpp,amr,caf', 'max:20480']
                : ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
