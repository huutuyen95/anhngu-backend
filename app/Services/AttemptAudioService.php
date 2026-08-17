<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\TestAttempt;
use App\Repositories\AttemptRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Nộp / xoá audio trả lời cho câu speaking. Tách khỏi TestAttemptController để controller mỏng.
 */
class AttemptAudioService
{
    private const DISK = 'public';

    private const DIRECTORY = 'answers/audio';

    public function __construct(private readonly AttemptRepository $attempts) {}

    /**
     * Lưu file audio mới cho (attempt, question), xoá file cũ trên disk nếu có.
     */
    public function upload(TestAttempt $attempt, Question $question, UploadedFile $file): AttemptAnswer
    {
        $this->assertSpeakingQuestionOfAttempt($attempt, $question);

        $existing = $this->attempts->answer($attempt, $question);

        if ($existing?->answer_file_url) {
            $this->deleteFile($existing->answer_file_url);
        }

        $path = $file->store(self::DIRECTORY, self::DISK);

        return $this->attempts->upsertAudioAnswer($attempt, $question, asset('storage/'.$path));
    }

    /**
     * Xoá file audio đã nộp (nút "xoá để ghi lại") — xoá cả trên disk lẫn trong DB.
     */
    public function delete(TestAttempt $attempt, Question $question): void
    {
        $this->assertSpeakingQuestionOfAttempt($attempt, $question);

        $answer = $this->attempts->answer($attempt, $question);

        if (! $answer?->answer_file_url) {
            return;
        }

        $this->deleteFile($answer->answer_file_url);
        $this->attempts->upsertAudioAnswer($attempt, $question, null);
    }

    private function assertSpeakingQuestionOfAttempt(TestAttempt $attempt, Question $question): void
    {
        abort_unless($question->type === QuestionType::Speaking, 422, 'Câu hỏi không phải dạng speaking.');

        $belongsToAttempt = $this->attempts->questionBelongsToTest($question, $attempt);

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
