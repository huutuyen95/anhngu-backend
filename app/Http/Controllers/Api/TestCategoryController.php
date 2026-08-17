<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Test\ListTestCategoriesRequest;
use App\Http\Requests\Test\SyncTestCategoriesRequest;
use App\Services\TestCategoryService;
use Illuminate\Http\JsonResponse;

class TestCategoryController extends Controller
{
    public function __construct(private readonly TestCategoryService $categories) {}

    public function index(ListTestCategoriesRequest $request): JsonResponse
    {
        $classroomId = isset($request->validated()['classroom_id']) ? (int) $request->validated()['classroom_id'] : null;

        return response()->json(['data' => $this->categories->tree($classroomId)]);
    }

    public function sync(SyncTestCategoriesRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->categories->sync(
            $data['classroom_id'] ?? null,
            $data['categories'],
            $data['deleted_ids'] ?? [],
        );

        return response()->json([
            'data' => $this->categories->tree($data['classroom_id'] ?? null),
            'moved_count' => $result['moved_count'],
        ]);
    }
}
