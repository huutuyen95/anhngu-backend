<?php

namespace App\Services;

use App\Models\TestCategory;
use App\Repositories\TestCategoryRepository;

class TestCategoryService
{
    public const UNCATEGORIZED = 'Chưa phân loại';

    public function __construct(private readonly TestCategoryRepository $categories) {}

    /**
     * Cây thư mục của một NHÓM (exam|exercise), 2 cấp, kèm tests_count từng nhánh.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(string $group): array
    {
        $roots = $this->categories->roots($group);

        return $roots->map(fn (TestCategory $c) => $this->node($c))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function node(TestCategory $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'parent_id' => $c->parent_id,
            'order' => $c->order,
            'tests_count' => (int) ($c->tests_count ?? 0),
            'children' => $c->relationLoaded('children')
                ? $c->children->map(fn (TestCategory $child) => $this->node($child))->all()
                : [],
        ];
    }

    /**
     * Đồng bộ cả cây thư mục của một lớp trong 1 transaction:
     * tạo mới (id null) · cập nhật tên/parent/order · xoá deleted_ids (dồn đề về "Chưa phân loại").
     *
     * @param  array<int, array<string, mixed>>  $categories
     * @param  array<int, int>  $deletedIds
     * @return array{moved_count: int}
     */
    public function sync(string $group, array $categories, array $deletedIds): array
    {
        $rows = collect($categories)->map(fn (array $row) => [
            'id' => $row['id'] ?? null,
            'name' => trim((string) $row['name']),
            'parent_id' => $row['parent_id'] ?? null,
            'order' => (int) ($row['order'] ?? 0),
        ])->filter(fn (array $row) => $row['name'] !== '')->all();

        return ['moved_count' => $this->categories->sync($group, $rows, $deletedIds)];
    }

    public function uncategorized(string $group): TestCategory
    {
        return $this->categories->uncategorized($group);
    }
}
