<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestDetailResource;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\AttemptAudioService;
use App\Services\TestGradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestAttemptController extends Controller
{
    public function __construct(
        private readonly TestGradingService $gradingService,
        private readonly AttemptAudioService $audioService,
    ) {}

    public function start(Request $request, Test $test)
    {
        abort_unless($test->is_published, 404);

        $attempt = DB::transaction(function () use ($request, $test) {
            // Dọn attempt in_progress cũ chưa nộp (nếu có) trước khi tạo lượt mới.
            $stale = TestAttempt::where('user_id', $request->user()->id)
                ->where('test_id', $test->id)
                ->where('status', 'in_progress')
                ->first();

            if ($stale) {
                $stale->answers()->delete();
                $stale->delete();
            }

            return TestAttempt::create([
                'user_id' => $request->user()->id,
                'test_id' => $test->id,
                'status' => 'in_progress',
                'started_at' => now(),
                'question_count' => $test->questionCount(),
            ]);
        });

        $deadline = $attempt->started_at->clone()->addMinutes($test->duration_minutes);

        return response()->json([
            'attempt_id' => $attempt->id,
            'started_at' => $attempt->started_at,
            'deadline' => $deadline,
        ]);
    }

    public function saveAnswers(Request $request, TestAttempt $attempt)
    {
        abort_if($attempt->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer', 'exists:questions,id'],
            'answers.*.question_option_id' => ['nullable', 'integer', 'exists:question_options,id'],
            'answers.*.answer_text' => ['nullable', 'string'],
        ]);

        foreach ($data['answers'] as $answer) {
            AttemptAnswer::updateOrCreate(
                [
                    'test_attempt_id' => $attempt->id,
                    'question_id' => $answer['question_id'],
                ],
                [
                    'question_option_id' => $answer['question_option_id'] ?? null,
                    'answer_text' => $answer['answer_text'] ?? null,
                ]
            );
        }

        return response()->json(['message' => 'Đã lưu bài làm.']);
    }

    public function uploadAudio(Request $request, TestAttempt $attempt, Question $question)
    {
        abort_if($attempt->user_id !== $request->user()->id, 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:mp3,m4a,wav,ogg,aac,webm', 'max:20480'],
        ]);

        $answer = $this->audioService->upload($attempt, $question, $request->file('file'));

        return response()->json(['url' => $answer->answer_file_url]);
    }

    public function deleteAudio(Request $request, TestAttempt $attempt, Question $question)
    {
        abort_if($attempt->user_id !== $request->user()->id, 403);

        $this->audioService->delete($attempt, $question);

        return response()->json(['message' => 'Đã xoá bản ghi âm.']);
    }

    public function submit(Request $request, TestAttempt $attempt)
    {
        abort_if($attempt->user_id !== $request->user()->id, 403);

        return response()->json($this->gradingService->submit($attempt));
    }

    public function result(Request $request, TestAttempt $attempt)
    {
        abort_if($attempt->user_id !== $request->user()->id, 403);

        $attempt->load('answers');

        $test = $attempt->test()
            ->with([
                'parts' => fn ($query) => $query->orderBy('order'),
                'parts.sections' => fn ($query) => $query->orderBy('order'),
                'parts.sections.questions' => fn ($query) => $query->orderBy('order'),
                'parts.sections.questions.options',
            ])
            ->firstOrFail();

        return response()->json([
            'id' => $attempt->id,
            'total_score' => (float) $attempt->total_score,
            'correct_count' => $attempt->correct_count,
            'question_count' => $attempt->question_count,
            'submitted_at' => $attempt->submitted_at,
            'test' => new TestDetailResource($test, revealAnswers: true),
            'answers' => $attempt->answers->map(fn (AttemptAnswer $answer) => [
                'question_id' => $answer->question_id,
                'question_option_id' => $answer->question_option_id,
                'answer_text' => $answer->answer_text,
                'is_correct' => $answer->is_correct,
            ])->values(),
        ]);
    }
}
