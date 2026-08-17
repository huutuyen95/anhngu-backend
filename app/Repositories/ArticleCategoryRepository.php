<?php

namespace App\Repositories;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArticleCategoryRepository
{
    public function allWithCounts(): Collection
    {
        return ArticleCategory::query()
            ->withCount('articles')
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn (ArticleCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'order' => $category->order,
                'articles_count' => $category->articles_count,
            ]);
    }

    public function sync(array $categories, array $deletedIds): void
    {
        DB::transaction(function () use ($categories, $deletedIds) {
            if ($deletedIds !== []) {
                Article::whereIn('category_id', $deletedIds)->update(['category_id' => null]);
                ArticleCategory::whereIn('id', $deletedIds)->delete();
            }

            foreach ($categories as $category) {
                $values = ['name' => $category['name'], 'order' => $category['order']];
                if ($category['id'] !== null) {
                    ArticleCategory::whereKey($category['id'])->update($values);
                } else {
                    ArticleCategory::firstOrCreate(['name' => $values['name']], ['order' => $values['order']]);
                }
            }
        });
    }

    public function published(): Collection
    {
        return ArticleCategory::query()
            ->whereHas('articles', fn ($query) => $query->where('is_published', true))
            ->withCount(['articles' => fn ($query) => $query->where('is_published', true)])
            ->orderBy('order')
            ->get(['id', 'name', 'order']);
    }
}
