<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Test\SaveStructureRequest;
use App\Http\Requests\Test\StoreTestRequest;
use App\Http\Requests\Test\UpdateTestRequest;
use App\Http\Resources\TestDetailResource;
use App\Http\Resources\TestResource;
use App\Models\Test;
use App\Services\AdminTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTestController extends Controller
{
    public function __construct(private readonly AdminTestService $tests) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->tests->list(
            $request->only(['q', 'skill', 'is_published', 'sort', 'dir', 'per_page']),
            $request->user(),
        );

        return response()->json([
            'data' => TestResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreTestRequest $request): JsonResponse
    {
        $test = $this->tests->create($request->validated(), $request->user());

        return response()->json(['test' => new TestResource($test)], 201);
    }

    public function show(Test $test): JsonResponse
    {
        $test->load([
            'parts' => fn ($q) => $q->orderBy('order'),
            'parts.sections' => fn ($q) => $q->orderBy('order'),
            'parts.sections.questions' => fn ($q) => $q->orderBy('order'),
            'parts.sections.questions.options',
        ]);

        return response()->json(['test' => new TestDetailResource($test, revealAnswers: true, forTeacher: true)]);
    }

    public function update(UpdateTestRequest $request, Test $test): JsonResponse
    {
        $updated = $this->tests->update($test, $request->validated());

        return response()->json(['test' => new TestResource($updated)]);
    }

    public function destroy(Request $request, Test $test): JsonResponse
    {
        $attemptsCount = $test->attempts()->count();

        if ($attemptsCount > 0 && ! $request->boolean('force')) {
            return response()->json([
                'attempts_count' => $attemptsCount,
                'message' => "Đề đã có {$attemptsCount} bài làm. Xoá sẽ mất toàn bộ, không khôi phục được.",
            ], 409);
        }

        $this->tests->delete($test);

        return response()->json(['message' => 'Đã xoá đề thi.']);
    }

    public function duplicate(Test $test, Request $request): JsonResponse
    {
        $copy = $this->tests->duplicate($test, $request->user());

        return response()->json(['test' => new TestResource($copy)], 201);
    }

    public function moveCategory(Request $request, Test $test): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:test_categories,id'],
        ]);

        $updated = $this->tests->moveCategory($test, $data['category_id'] ?? null);

        return response()->json(['test' => new TestResource($updated)]);
    }

    public function preflight(Test $test): JsonResponse
    {
        return response()->json($this->tests->preflight($test));
    }

    public function saveStructure(SaveStructureRequest $request, Test $test): JsonResponse
    {
        $updated = $this->tests->saveStructure($test, $request->validated());

        return response()->json(['test' => new TestDetailResource($updated, revealAnswers: true, forTeacher: true)]);
    }
}
