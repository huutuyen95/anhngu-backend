<?php

namespace App\Repositories;

use App\Models\Article;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ArticleRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Article::query()
            ->with('category:id,name')
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where('title', 'like', "%{$value}%"))
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->where('category_id', $value))
            ->when(array_key_exists('is_published', $filters), fn ($query) => $query->where('is_published', $filters['is_published']))
            ->latest('updated_at')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data): Article
    {
        return Article::create($data)->load('category:id,name');
    }

    public function update(Article $article, array $data): Article
    {
        $article->update($data);

        return $article->fresh()->load('category:id,name');
    }

    public function load(Article $article): Article
    {
        return $article->load('category:id,name');
    }

    public function delete(Article $article): void
    {
        $article->delete();
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return Article::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function published(array $filters): Collection
    {
        return Article::query()
            ->where('is_published', true)
            ->with('category:id,name')
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->where('category_id', $value))
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where('title', 'like', "%{$value}%"))
            ->when(($filters['sort'] ?? 'newest') === 'oldest', fn ($query) => $query->oldest('published_at'), fn ($query) => $query->latest('published_at'))
            ->get();
    }

    public function incrementViewsAndLoad(Article $article): Article
    {
        $article->increment('view_count');

        return $article->load('category:id,name');
    }
}
