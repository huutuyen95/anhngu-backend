<?php

namespace App\Http\Requests\Deck;

class CompleteDeckSessionRequest extends StudyDeckRequest
{
    public function rules(): array
    {
        return parent::rules() + ['duration_seconds' => ['nullable', 'integer', 'min:0']];
    }
}
