<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ListAssignableContentRequest;
use App\Services\ContentService;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    public function __construct(private readonly ContentService $content) {}

    public function index(ListAssignableContentRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->content->list($request->validated())]);
    }
}
