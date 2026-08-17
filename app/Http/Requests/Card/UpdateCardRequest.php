<?php

namespace App\Http\Requests\Card;

class UpdateCardRequest extends StoreCardRequest
{
    public function rules(): array
    {
        return collect(parent::rules())->map(fn (array $rules) => array_merge(['sometimes'], $rules))->all();
    }
}
