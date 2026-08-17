<?php

namespace App\Http\Requests\Article;

class UpdateArticleRequest extends StoreArticleRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['title'][0] = 'sometimes';

        return $rules;
    }
}
