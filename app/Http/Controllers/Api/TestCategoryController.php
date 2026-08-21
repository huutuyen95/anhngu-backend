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
        $group = $request->validated()['group'] ?? 'exam';

        return response()->json(['data' => $this->categories->tree($group)]);
    }

    public function sync(SyncTestCategoriesRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->categories->sync(
            $data['group'],
            $data['categories'],
            $data['deleted_ids'] ?? [],
        );

        return response()->json([
            'data' => $this->categories->tree($data['group']),
            'moved_count' => $result['moved_count'],
        ]);
    }
}
