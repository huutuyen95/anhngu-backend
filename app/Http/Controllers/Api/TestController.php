<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Test\ListStudentTestsRequest;
use App\Http\Resources\StudentTestResource;
use App\Http\Resources\TestDetailResource;
use App\Models\Test;
use App\Services\StudentTestService;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    public function __construct(private readonly StudentTestService $tests) {}

    public function index(ListStudentTestsRequest $request): JsonResponse
    {
        $filters = $request->validated();
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
        return new TestDetailResource($this->tests->detail($test), revealAnswers: false);
    }
}
