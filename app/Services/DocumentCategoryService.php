<?php

namespace App\Services;

use App\Repositories\DocumentCategoryRepository;
use Illuminate\Support\Collection;

class DocumentCategoryService
{
    public function __construct(private readonly DocumentCategoryRepository $categories) {}

    public function all(): Collection
    {
        return $this->categories->allWithCounts();
    }

    public function sync(array $data): array
    {
        $rows = collect($data['categories'])->map(fn (array $row, int $index) => [
            'id' => $row['id'] ?? null,
            'name' => trim($row['name']),
            'order' => $row['order'] ?? $index + 1,
        ])->all();
        $moved = $this->categories->sync($rows, $data['deleted_ids'] ?? []);

        return ['data' => $this->all(), 'moved_count' => $moved];
    }
}
