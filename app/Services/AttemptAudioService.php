<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\TestAttempt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Nộp / xoá audio trả lời cho câu speaking. Tách khỏi TestAttemptController để controller mỏng.
 */
class AttemptAudioService
{
    private const DISK = 'public';

    private const DIRECTORY = 'answers/audio';

    /**
     * Lưu file audio mới cho (attempt, question), xoá file cũ trên disk nếu có.
     */
    public function upload(TestAttempt $attempt, Question $question, UploadedFile $file): AttemptAnswer
    {
        $this->assertSpeakingQuestionOfAttempt($attempt, $question);

        $existing = AttemptAnswer::where('test_attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();

        if ($existing?->answer_file_url) {
            $this->deleteFile($existing->answer_file_url);
        }

        $path = $file->store(self::DIRECTORY, self::DISK);

        return AttemptAnswer::updateOrCreate(
            ['test_attempt_id' => $attempt->id, 'question_id' => $question->id],
            ['answer_file_url' => asset('storage/'.$path)]
        );
    }

    /**
     * Xoá file audio đã nộp (nút "xoá để ghi lại") — xoá cả trên disk lẫn trong DB.
     */
    public function delete(TestAttempt $attempt, Question $question): void
    {
        $this->assertSpeakingQuestionOfAttempt($attempt, $question);

        $answer = AttemptAnswer::where('test_attempt_id', $attempt->id)
            ->where('question_id', $question->id)
            ->first();

        if (! $answer?->answer_file_url) {
            return;
        }

        $this->deleteFile($answer->answer_file_url);
        $answer->update(['answer_file_url' => null]);
    }

    private function assertSpeakingQuestionOfAttempt(TestAttempt $attempt, Question $question): void
    {
        abort_unless($question->type === QuestionType::Speaking, 422, 'Câu hỏi không phải dạng speaking.');

        $belongsToAttempt = Question::where('id', $question->id)
            ->whereHas('section.part', fn ($q) => $q->where('test_id', $attempt->test_id))
            ->exists();

        abort_unless($belongsToAttempt, 404);
    }

    private function deleteFile(string $url): void
    {
        $prefix = asset('storage/');

        if (! str_starts_with($url, $prefix)) {
            return;
        }

        $path = substr($url, strlen($prefix));

        if (Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
