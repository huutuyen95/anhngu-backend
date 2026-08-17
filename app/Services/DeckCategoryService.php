<?php

namespace App\Services;

use App\Repositories\DeckCategoryRepository;
use Illuminate\Support\Collection;

class DeckCategoryService
{
    public function __construct(private readonly DeckCategoryRepository $categories) {}

    public function all(): Collection
    {
        return $this->categories->allWithCounts();
    }

    public function sync(array $data): Collection
    {
        $rows = collect($data['categories'])->map(fn (array $row, int $index) => [
            'id' => $row['id'] ?? null,
            'name' => trim($row['name']),
            'order' => $row['order'] ?? $index + 1,
        ])->all();
        $this->categories->sync($rows, $data['deleted_ids'] ?? []);

        return $this->all();
    }
}
