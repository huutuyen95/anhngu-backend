<?php

namespace App\Services;

use App\Repositories\ArticleCategoryRepository;
use Illuminate\Support\Collection;

class ArticleCategoryService
{
    public function __construct(private readonly ArticleCategoryRepository $categories) {}

    public function all(): Collection
    {
        return $this->categories->allWithCounts();
    }

    public function sync(array $data): Collection
    {
        $categories = collect($data['categories'])->map(fn (array $category, int $index) => [
            'id' => $category['id'] ?? null,
            'name' => trim($category['name']),
            'order' => $category['order'] ?? $index + 1,
        ])->all();

        $this->categories->sync($categories, $data['deleted_ids'] ?? []);

        return $this->all();
    }
}
