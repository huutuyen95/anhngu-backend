<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attempt\GradeAttemptRequest;
use App\Http\Requests\Attempt\ListAttemptsRequest;
use App\Http\Resources\AttemptDetailResource;
use App\Http\Resources\AttemptResource;
use App\Http\Responses\ApiResponse;
use App\Models\TestAttempt;
use App\Services\AttemptGradingService;
use Illuminate\Http\JsonResponse;

class AdminAttemptController extends Controller
{
    public function __construct(private readonly AttemptGradingService $attempts) {}

    public function index(ListAttemptsRequest $request): JsonResponse
    {
        $page = $this->attempts->list($request->validated());

        return ApiResponse::paginated(AttemptResource::collection($page->items()), $page);
    }

    public function show(TestAttempt $attempt): JsonResponse
    {
        return ApiResponse::resource(new AttemptDetailResource($this->attempts->show($attempt)), 'attempt');
    }

    public function grade(GradeAttemptRequest $request, TestAttempt $attempt): JsonResponse
    {
        $graded = $this->attempts->grade($attempt, $request->validated()['answers'], $request->user());

        return ApiResponse::resource(new AttemptDetailResource($graded), 'attempt');
    }
}
