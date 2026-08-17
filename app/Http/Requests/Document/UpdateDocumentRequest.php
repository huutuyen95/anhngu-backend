<?php

namespace App\Http\Requests\Document;

class UpdateDocumentRequest extends StoreDocumentRequest
{
    public function rules(): array
    {
        return collect(parent::rules())->map(fn (array $rules) => array_merge(['sometimes'], $rules))->all();
    }
}
