<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestDetailResource;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use Illuminate\Http\Request;

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

        // Mỗi (user, test) chỉ còn tối đa 1 dòng submitted = lượt điểm cao nhất.
        $bestAttempts = TestAttempt::where('user_id', $request->user()->id)
            ->where('status', 'submitted')
            ->whereIn('test_id', $tests->pluck('id'))
            ->get()
            ->keyBy('test_id');

        $data = $tests->map(function (Test $test) use ($questionCounts, $bestAttempts) {
            $best = $bestAttempts->get($test->id);

            return [
                'id' => $test->id,
                'title' => $test->title,
                'slug' => $test->slug,
                'skill' => $test->skill->value,
                'duration_minutes' => $test->duration_minutes,
                'total_score' => (float) $test->total_score,
                'question_count' => (int) ($questionCounts[$test->id] ?? 0),
                'attempt' => $best ? [
                    'status' => 'submitted',
                    'best_score' => (float) $best->total_score,
                    'attempt_count' => $best->attempt_count,
                    'last_attempted_at' => $best->last_attempted_at,
                ] : null,
            ];
        })->values();

        return response()->json($data);
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
