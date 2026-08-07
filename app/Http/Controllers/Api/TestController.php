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
        $tests = $this->tests->list($request->user());

        return response()->json(StudentTestResource::collection($tests));
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
