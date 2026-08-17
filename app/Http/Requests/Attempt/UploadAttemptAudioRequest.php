<?php

namespace App\Http\Requests\Attempt;

class UploadAttemptAudioRequest extends OwnedAttemptRequest
{
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:mp3,m4a,wav,ogg,aac,webm', 'max:20480']];
    }
}
