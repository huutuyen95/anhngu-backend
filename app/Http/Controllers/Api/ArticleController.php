<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Article\ListArticlesRequest;
use App\Http\Requests\Article\PublishArticleRequest;
use App\Http\Requests\Article\StoreArticleRequest;
use App\Http\Requests\Article\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Services\ArticleService;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    public function __construct(private readonly ArticleService $articles) {}

    public function index(ListArticlesRequest $request): JsonResponse
    {
        $page = $this->articles->paginate($request->validated());

        return response()->json([
            'data' => ArticleResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $article = $this->articles->create($request->validated(), $request->user()->id);

        return response()->json(['article' => new ArticleResource($article)], 201);
    }

    public function show(Article $article): JsonResponse
    {
        return response()->json(['article' => new ArticleResource($this->articles->show($article))]);
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        return response()->json([
            'article' => new ArticleResource($this->articles->update($article, $request->validated())),
        ]);
    }

    public function publish(PublishArticleRequest $request, Article $article): JsonResponse
    {
        $article = $this->articles->publish($article, $request->boolean('is_published'));

        return response()->json(['is_published' => $article->is_published]);
    }

    public function destroy(Article $article): JsonResponse
    {
        $this->articles->delete($article);

        return response()->json(['message' => 'Đã xoá bài viết.']);
    }
}
