<?php

namespace App\Http\Requests\Classroom;

class UpdateClassSessionRequest extends StoreClassSessionRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'][0] = 'sometimes';

        return $rules;
    }
}
