<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chi tiết 1 bài làm cho giáo viên chấm: cây đề (kèm đáp án đúng) ghép với câu trả lời của
 * học sinh — kể cả câu writing (answer_text/feedback/graded_by/graded_at).
 *
 * @mixin \App\Models\TestAttempt
 */
class AttemptDetailResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $answersByQuestion = $this->answers->keyBy('question_id');

        return [
            'id' => $this->id,
            'status' => $this->status,
            'total_score' => $this->total_score !== null ? (float) $this->total_score : null,
            'correct_count' => $this->correct_count,
            'question_count' => $this->question_count,
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'student' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'test' => [
                'id' => $this->test->id,
                'title' => $this->test->title,
                'skill' => $this->test->skill->value,
                'total_score' => (float) $this->test->total_score,
                'word_limit' => $this->test->word_limit,
                'rubric' => $this->test->rubric,
                'parts' => $this->test->parts->map(fn ($part) => [
                    'id' => $part->id,
                    'order' => $part->order,
                    'title' => $part->title,
                    'sections' => $part->sections->map(fn ($section) => [
                        'id' => $section->id,
                        'order' => $section->order,
                        'instruction' => $section->instruction,
                        'passage' => $section->passage,
                        'questions' => $section->questions->map(function ($question) use ($answersByQuestion) {
                            $answer = $answersByQuestion->get($question->id);

                            return [
                                'id' => $question->id,
                                'order' => $question->order,
                                'type' => $question->type->value,
                                'content' => $question->content,
                                'score' => (float) $question->score,
                                'explanation' => $question->explanation,
                                'images' => $question->images,
                                'record_limit_seconds' => $question->record_limit_seconds,
                                'options' => $question->options->map(fn ($o) => [
                                    'id' => $o->id,
                                    'label' => $o->label,
                                    'content' => $o->content,
                                    'is_correct' => (bool) $o->is_correct,
                                ])->values(),
                                'answer' => $answer ? [
                                    'question_option_id' => $answer->question_option_id,
                                    'answer_text' => $answer->answer_text,
                                    'answer_file_url' => $answer->answer_file_url,
                                    'is_correct' => $answer->is_correct,
                                    'score' => (float) $answer->score,
                                    'feedback' => $answer->feedback,
                                    'graded_by' => $answer->gradedBy?->name,
                                    'graded_at' => $answer->graded_at?->toIso8601String(),
                                ] : null,
                            ];
                        })->values(),
                    ])->values(),
                ])->values(),
            ],
        ];
    }
}
