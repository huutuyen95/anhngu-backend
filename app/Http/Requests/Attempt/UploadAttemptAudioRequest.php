<?php

namespace App\Http\Requests\Attempt;

class UploadAttemptAudioRequest extends OwnedAttemptRequest
{
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:mp3,m4a,mp4,wav,ogg,oga,aac,webm,3gp,3gpp,amr,caf', 'max:20480']];
    }
}
