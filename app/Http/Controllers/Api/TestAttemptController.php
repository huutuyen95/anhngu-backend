<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestDetailResource;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Services\AttemptAudioService;
use App\Services\AttemptStartService;
use App\Services\TestGradingService;
use Illuminate\Http\Request;

class TestAttemptController extends Controller
{
    public function __construct(
        private readonly TestGradingService $gradingService,
        private readonly AttemptAudioService $audioService,
        private readonly AttemptStartService $startService,
    ) {}

    /**
     * Mở lượt làm bài. `mission_id` (tuỳ chọn) = vào từ lớp học, làm bài cô giao; không có =
     * tự luyện trong Thư viện. Hai nguồn tách hẳn nhau — xem AttemptStartService.
     */
    public function start(Request $request, Test $test)
    {
        abort_unless($test->is_published, 404);

        $data = $request->validate([
            'mission_id' => ['nullable', 'integer'],
        ]);

        $attempt = $this->startService->start($request->user(), $test, $data['mission_id'] ?? null);

        $deadline = $attempt->started_at->clone()->addMinutes($test->duration_minutes);

        return response()->json([
            'attempt_id' => $attempt->id,
            'started_at' => $attempt->started_at,
            'deadline' => $deadline,
            'mission_id' => $attempt->mission_id,
            'source' => $attempt->source,
        ]);
    }

    public function saveAnswers(Request $request, TestAttempt $attempt)
    {
        abort_if($attempt->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            // `present` chứ không phải `required`: học viên chưa làm câu nào thì mảng
            // rỗng vẫn là payload hợp lệ. Dùng `required` khiến lượt nộp lúc hết giờ
            // mà bỏ trắng cả bài bị 422 và không nộp được.
            'answers' => ['present', 'array'],
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

        $result = $this->gradingService->submit($attempt);
        $result['grading'] = $this->gradingConfig($attempt);
        // Màn kết quả vẽ ngay từ payload này (lưu sessionStorage) trước khi GET /result về,
        // nên nó cũng phải biết lượt vừa nộp thuộc nguồn nào.
        $result['source'] = $attempt->source;
        $result['mission'] = $this->missionContext($attempt);

        return response()->json($result);
    }

    /**
     * Ngữ cảnh của lượt làm để FE biết đang ở màn nào mà vẽ cho khác nhau: bài cô giao
     * trong lớp (kèm lớp/buổi/hạn nộp/số lượt còn) hay em tự luyện ở Thư viện.
     *
     * `null` = tự luyện. Hai luồng dùng chung component nên thiếu khối này thì màn kết quả
     * của lớp lại hiện nút "Về Nhiệm vụ" / "Làm lại từ đầu" của Thư viện.
     */
    private function missionContext(TestAttempt $attempt): ?array
    {
        if (! $attempt->mission_id) {
            return null;
        }

        $mission = $attempt->mission()->with(['classroom:id,name', 'classSession:id,title,order'])->first();

        if (! $mission) {
            return null;
        }

        $allowed = max(1, (int) ($mission->attempts_allowed ?? 1));

        return [
            'id' => $mission->id,
            'classroom_id' => $mission->classroom_id,
            'classroom_name' => $mission->classroom?->name,
            'session_title' => $mission->classSession?->title,
            'session_order' => $mission->classSession?->order,
            'due_date' => $mission->due_date?->toDateString(),
            'attempts_allowed' => $allowed,
            'attempts_used' => $mission->attemptsUsed(),
        ];
    }

    /** Cấu hình hiển thị điểm (số thập phân, điểm đạt) — theo snapshot lúc bắt đầu. */
    private function gradingConfig(TestAttempt $attempt): array
    {
        return [
            'decimals' => (int) $attempt->configValue('grading.decimals', 1),
            'pass_score' => (float) $attempt->configValue('grading.pass_score', 5.0),
        ];
    }

    public function result(Request $request, TestAttempt $attempt)
    {
        abort_if($attempt->user_id !== $request->user()->id, 403);

        $attempt->load('answers.gradedBy:id,name');

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
            // `status` + phần chấm tay bên dưới để màn kết quả của học viên biết bài
            // writing đã được cô chấm hay còn chờ, và hiện điểm/nhận xét của cô.
            'status' => $attempt->status,
            'source' => $attempt->source,
            'mission' => $this->missionContext($attempt),
            'total_score' => (float) $attempt->total_score,
            'correct_count' => $attempt->correct_count,
            'question_count' => $attempt->question_count,
            'started_at' => $attempt->started_at,
            'submitted_at' => $attempt->submitted_at,
            'grading' => $this->gradingConfig($attempt),
            'test' => new TestDetailResource($test, revealAnswers: true),
            'answers' => $attempt->answers->map(fn (AttemptAnswer $answer) => [
                'question_id' => $answer->question_id,
                'question_option_id' => $answer->question_option_id,
                'answer_text' => $answer->answer_text,
                'is_correct' => $answer->is_correct,
                'score' => (float) $answer->score,
                'feedback' => $answer->feedback,
                'graded_by' => $answer->gradedBy?->name,
                'graded_at' => $answer->graded_at,
            ])->values(),
        ]);
    }

    /**
     * Trạng thái lượt làm để FE khôi phục màn thi: hạn nộp (tính server-side từ
     * started_at + thời lượng, KHÔNG truyền qua URL), đáp án đã lưu, và bộ đếm thoát tab.
     */
    public function show(Request $request, TestAttempt $attempt)
    {
        abort_if($attempt->user_id !== $request->user()->id, 403);

        $attempt->load(['test:id,duration_minutes', 'answers']);

        return response()->json([
            'id' => $attempt->id,
            'status' => $attempt->status,
            'source' => $attempt->source,
            'mission' => $this->missionContext($attempt),
            'started_at' => $attempt->started_at,
            'deadline' => $attempt->deadlineAt(),
            'tab_exit_count' => $attempt->tab_exit_count,
            'tab_exit_limit' => (int) $attempt->configValue('exam.leave_limit', TestAttempt::TAB_EXIT_LIMIT),
            'tab_exit_action' => $attempt->configValue('exam.leave_action', 'warn'),
            'block_copy' => (bool) $attempt->configValue('exam.block_copy', true),
            'autosubmit_on_timeout' => (bool) $attempt->configValue('exam.autosubmit_on_timeout', true),
            'answers' => $attempt->answers->map(fn (AttemptAnswer $answer) => [
                'question_id' => $answer->question_id,
                'question_option_id' => $answer->question_option_id,
                'answer_text' => $answer->answer_text,
            ])->values(),
        ]);
    }

    /**
     * Ghi nhận một lần học sinh rời khỏi tab làm bài. Đếm server-side để reload không
     * reset được. Vượt quá TAB_EXIT_LIMIT → tự nộp bài ngay và trả kết quả cho FE.
     */
    public function tabExit(Request $request, TestAttempt $attempt)
    {
        abort_if($attempt->user_id !== $request->user()->id, 403);

        // Lấy theo snapshot lúc bắt đầu — đổi cấu hình giữa chừng không ảnh hưởng lượt này.
        $limit = (int) $attempt->configValue('exam.leave_limit', TestAttempt::TAB_EXIT_LIMIT);
        $action = $attempt->configValue('exam.leave_action', 'warn');

        // Đã nộp (tự động hoặc thủ công) → không đếm thêm, không nộp lại.
        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'tab_exit_count' => $attempt->tab_exit_count,
                'tab_exit_limit' => $limit,
                'tab_exit_action' => $action,
                'auto_submitted' => true,
                'status' => $attempt->status,
            ]);
        }

        $attempt->increment('tab_exit_count');
        $attempt->refresh();

        // Chỉ tự nộp khi cấu hình là 'autosubmit'. 'warn'/'log' chỉ đếm.
        if ($action === 'autosubmit' && $attempt->tab_exit_count > $limit) {
            $result = $this->gradingService->submit($attempt);

            return response()->json([
                'tab_exit_count' => $attempt->tab_exit_count,
                'tab_exit_limit' => $limit,
                'tab_exit_action' => $action,
                'auto_submitted' => true,
                'reason' => 'tab_exit_exceeded',
                'result' => $result,
            ]);
        }

        return response()->json([
            'tab_exit_count' => $attempt->tab_exit_count,
            'tab_exit_limit' => $limit,
            'tab_exit_action' => $action,
            'auto_submitted' => false,
        ]);
    }
}
