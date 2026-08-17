<?php

namespace App\Http\Requests\Attempt;

use Illuminate\Foundation\Http\FormRequest;

class OwnedAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attempt = $this->route('attempt');

        return $attempt && $attempt->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        return [];
    }
}
