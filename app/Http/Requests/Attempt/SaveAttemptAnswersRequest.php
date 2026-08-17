<?php

namespace App\Http\Requests\Attempt;

class SaveAttemptAnswersRequest extends OwnedAttemptRequest
{
    public function rules(): array
    {
        return ['answers' => ['present', 'array'], 'answers.*.question_id' => ['required', 'integer', 'exists:questions,id'], 'answers.*.question_option_id' => ['nullable', 'integer', 'exists:question_options,id'], 'answers.*.answer_text' => ['nullable', 'string']];
    }
}
