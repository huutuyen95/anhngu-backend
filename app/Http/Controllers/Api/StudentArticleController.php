<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Article\ListStudentArticlesRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Article;
use App\Services\StudentArticleService;
use Illuminate\Http\JsonResponse;

class StudentArticleController extends Controller
{
    public function __construct(private readonly StudentArticleService $articles) {}

    public function index(ListStudentArticlesRequest $request): JsonResponse
    {
        return ApiResponse::collection(ArticleResource::collection($this->articles->list($request->validated())));
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => $this->articles->categories()]);
    }

    public function read(Article $article): JsonResponse
    {
        return ApiResponse::resource(new ArticleResource($this->articles->read($article)), 'article');
    }
}
