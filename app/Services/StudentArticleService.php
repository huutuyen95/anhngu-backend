<?php

namespace App\Services;

use App\Models\Article;
use App\Repositories\ArticleCategoryRepository;
use App\Repositories\ArticleRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class StudentArticleService
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly ArticleCategoryRepository $categories,
    ) {}

    public function list(array $filters): Collection
    {
        return $this->articles->published($filters);
    }

    public function categories(): SupportCollection
    {
        return $this->categories->published();
    }

    public function read(Article $article): Article
    {
        abort_unless($article->is_published, 404);

        return $this->articles->incrementViewsAndLoad($article);
    }
}
