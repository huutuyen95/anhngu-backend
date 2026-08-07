<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestDetailResource;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TestController extends Controller
{
    public function index(Request $request)
    {
        $tests = Test::where('is_published', true)->get();

        $questionCounts = Question::query()
            ->join('test_sections', 'questions.test_section_id', '=', 'test_sections.id')
            ->join('test_parts', 'test_sections.test_part_id', '=', 'test_parts.id')
            ->whereIn('test_parts.test_id', $tests->pluck('id'))
            ->selectRaw('test_parts.test_id as test_id, count(*) as question_count')
            ->groupBy('test_parts.test_id')
            ->pluck('question_count', 'test_id');

        // Lấy đủ 3 trạng thái đã nộp: đề có câu writing/speaking KHÔNG bao giờ thành `submitted`
        // mà đi `pending_review` → `graded`, lọc mỗi `submitted` là các đề đó hiện "chưa làm".
        // Dedup chỉ giữ 1 lượt đã finalize, nhưng `pending_review` không bị dedup
        // (TestGradingService::markPendingReview) nên vẫn có thể nằm cạnh một lượt `graded` cũ
        // → gom theo test_id thay vì keyBy để không mất dòng.
        $attemptsByTest = TestAttempt::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending_review', 'submitted', 'graded'])
            ->whereIn('test_id', $tests->pluck('id'))
            ->get()
            ->groupBy('test_id');

        $data = $tests->map(function (Test $test) use ($questionCounts, $attemptsByTest) {
            $attempts = $attemptsByTest->get($test->id);

            return [
                'id' => $test->id,
                'title' => $test->title,
                'slug' => $test->slug,
                'skill' => $test->skill->value,
                'duration_minutes' => $test->duration_minutes,
                'total_score' => (float) $test->total_score,
                'question_count' => (int) ($questionCounts[$test->id] ?? 0),
                'attempt' => $attempts ? $this->attemptSummary($attempts) : null,
            ];
        })->values();

        return response()->json($data);
    }

    /**
     * Gộp các lượt còn lại của một đề thành 1 dòng cho card.
     *
     * `status` lấy theo lượt mới nhất. `best_score` CHỈ lấy từ lượt đã finalize
     * (`submitted`/`graded`) — lượt `pending_review` mới có điểm tạm của phần tự chấm, chưa cộng
     * điểm cô chấm tay, nên trả ra sẽ thành điểm thấp giả. Đề chờ chấm lần đầu → `best_score` null.
     *
     * @param  Collection<int, TestAttempt>  $attempts
     * @return array<string, mixed>
     */
    private function attemptSummary(Collection $attempts): array
    {
        $latest = $attempts->sortByDesc('last_attempted_at')->first();
        $bestScore = $attempts
            ->whereIn('status', ['submitted', 'graded'])
            ->max(fn (TestAttempt $attempt) => (float) $attempt->total_score);

        return [
            'status' => $latest->status,
            'best_score' => $bestScore !== null ? (float) $bestScore : null,
            'attempt_count' => (int) $attempts->max('attempt_count'),
            'last_attempted_at' => $latest->last_attempted_at,
        ];
    }

    public function show(Test $test)
    {
        abort_unless($test->is_published, 404);

        $test->load([
            'parts' => fn ($query) => $query->orderBy('order'),
            'parts.sections' => fn ($query) => $query->orderBy('order'),
            'parts.sections.questions' => fn ($query) => $query->orderBy('order'),
            'parts.sections.questions.options',
        ]);

        return new TestDetailResource($test, revealAnswers: false);
    }
}
