<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Article\SyncArticleCategoriesRequest;
use App\Services\ArticleCategoryService;
use Illuminate\Http\JsonResponse;

class ArticleCategoryController extends Controller
{
    public function __construct(private readonly ArticleCategoryService $categories) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->categories->all()]);
    }

    public function sync(SyncArticleCategoriesRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->categories->sync($request->validated())]);
    }
}
