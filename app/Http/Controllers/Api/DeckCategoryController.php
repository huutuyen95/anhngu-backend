<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deck\SyncDeckCategoriesRequest;
use App\Services\DeckCategoryService;
use Illuminate\Http\JsonResponse;

class DeckCategoryController extends Controller
{
    public function __construct(private readonly DeckCategoryService $categories) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->categories->all()]);
    }

    public function sync(SyncDeckCategoriesRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->categories->sync($request->validated())]);
    }
}
