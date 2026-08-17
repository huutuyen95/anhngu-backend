<?php

namespace App\Http\Requests\Deck;

class UpdateCardProgressRequest extends StudyDeckRequest
{
    public function rules(): array
    {
        return parent::rules() + ['status' => ['required', 'in:new,learning,known']];
    }
}
