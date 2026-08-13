<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attempt\GradeAttemptRequest;
use App\Http\Resources\AttemptDetailResource;
use App\Http\Resources\AttemptResource;
use App\Models\TestAttempt;
use App\Services\AttemptGradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAttemptController extends Controller
{
    public function __construct(private readonly AttemptGradingService $attempts) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->attempts->list($request->only([
            'status', 'classroom_id', 'test_id', 'user_id', 'source', 'per_page',
        ]));

        return response()->json([
            'data' => AttemptResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(TestAttempt $attempt): JsonResponse
    {
        return response()->json(['attempt' => new AttemptDetailResource($this->attempts->show($attempt))]);
    }

    public function grade(GradeAttemptRequest $request, TestAttempt $attempt): JsonResponse
    {
        $graded = $this->attempts->grade($attempt, $request->validated()['answers'], $request->user());

        return response()->json(['attempt' => new AttemptDetailResource($graded)]);
    }
}
