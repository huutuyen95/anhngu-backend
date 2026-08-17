<?php

namespace App\Http\Requests\Deck;

class UpdateDeckRequest extends StoreDeckRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'][0] = 'sometimes';

        return $rules;
    }
}
