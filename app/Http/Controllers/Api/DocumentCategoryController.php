<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\SyncDocumentCategoriesRequest;
use App\Services\DocumentCategoryService;
use Illuminate\Http\JsonResponse;

class DocumentCategoryController extends Controller
{
    public function __construct(private readonly DocumentCategoryService $categories) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->categories->all()]);
    }

    public function sync(SyncDocumentCategoriesRequest $request): JsonResponse
    {
        return response()->json($this->categories->sync($request->validated()));
    }
}
