<?php

namespace App\Http\Resources;

use App\Enums\QuestionType;
use App\Models\TestAttempt;
use App\Services\Ai\GradingPrompt;
use App\Services\Ai\GradingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chi tiết 1 bài làm cho giáo viên chấm: cây đề (kèm đáp án đúng) ghép với câu trả lời của
 * học sinh — kể cả câu writing (answer_text/feedback/graded_by/graded_at).
 *
 * @mixin TestAttempt
 */
class AttemptDetailResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Lời nhắc hoàn chỉnh (yêu cầu chấm + đề bài + tiêu chí + bài làm) để cô copy một phát
     * dán sang ChatGPT.
     *
     * Dùng chung `GradingPrompt` với đường chấm tự động — cô và AI chấm theo CÙNG một bộ
     * tiêu chí, nên sau này bật tự động thì điểm không lệch hẳn so với giai đoạn chấm tay.
     */
    private function writingPrompt($question, $answer): ?string
    {
        $text = strip_tags((string) ($answer?->answer_text ?? ''));

        if ($text === '') {
            return null;   // em bỏ trống thì không có gì để nhờ chấm
        }

        $request = new GradingRequest(
            kind: 'writing',
            questionContent: (string) $question->content,
            hint: $question->hint,
            rubric: $this->test->rubric,
            maxScore: (float) $question->score,
            answerText: $text,
            wordLimit: $this->test->word_limit,
        );

        return GradingPrompt::forCopy($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $answersByQuestion = $this->answers->keyBy('question_id');
        // Đề xuất của AI — CHỈ khu chấm của cô mới thấy. Học viên không bao giờ đọc bảng này.
        $aiByQuestion = $this->relationLoaded('aiSuggestions')
            ? $this->aiSuggestions->keyBy('question_id')
            : collect();

        return [
            'id' => $this->id,
            'status' => $this->status,
            'source' => $this->source,
            'classroom' => $this->whenLoaded('classroom', fn () => $this->classroom ? [
                'id' => $this->classroom->id,
                'name' => $this->classroom->name,
            ] : null),
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
                        'questions' => $section->questions->map(function ($question) use ($answersByQuestion, $aiByQuestion) {
                            $answer = $answersByQuestion->get($question->id);

                            $ai = $aiByQuestion->get($question->id);

                            return [
                                'id' => $question->id,
                                'order' => $question->order,
                                'type' => $question->type->value,
                                'content' => $question->content,
                                'hint' => $question->hint,
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
                                // Khối chữ dựng sẵn để cô copy sang ChatGPT tự chấm. Chỉ câu
                                // VIẾT: cô chốt chấm tay bằng tài khoản ChatGPT của mình, còn
                                // câu nói thì cô tự nghe.
                                'ai_prompt' => $question->type === QuestionType::Writing
                                    ? $this->writingPrompt($question, $answer)
                                    : null,
                                // Gợi ý chấm của AI: cô đọc, sửa nếu cần rồi mới Lưu.
                                'ai_suggestion' => $ai ? [
                                    'score' => $ai->score !== null ? (float) $ai->score : null,
                                    'feedback' => $ai->feedback,
                                    'status' => $ai->status,
                                    'error' => $ai->error,
                                    'model' => $ai->model,
                                    'created_at' => $ai->created_at?->toIso8601String(),
                                ] : null,
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
