<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TestCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestCategoryController extends Controller
{
    public function __construct(private readonly TestCategoryService $categories) {}

    public function index(Request $request): JsonResponse
    {
        $classroomId = $request->filled('classroom_id') ? (int) $request->input('classroom_id') : null;

        return response()->json(['data' => $this->categories->tree($classroomId)]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'categories' => ['present', 'array'],
            'categories.*.id' => ['nullable', 'integer'],
            'categories.*.name' => ['required', 'string', 'max:120'],
            'categories.*.parent_id' => ['nullable', 'integer'],
            'categories.*.order' => ['nullable', 'integer'],
            'deleted_ids' => ['array'],
            'deleted_ids.*' => ['integer'],
        ]);

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
