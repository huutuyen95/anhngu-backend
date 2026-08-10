<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentTestResource;
use App\Http\Resources\TestDetailResource;
use App\Models\Test;
use App\Services\StudentTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function __construct(private readonly StudentTestService $tests) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['q', 'skill', 'status', 'sort', 'per_page']);
        $student = $request->user();

        $page = $this->tests->list($filters, $student);

        return response()->json([
            'data' => StudentTestResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                ...$this->tests->summary($filters, $student),
            ],
        ]);
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
