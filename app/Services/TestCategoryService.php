<?php

namespace App\Services;

use App\Models\TestCategory;
use Illuminate\Support\Facades\DB;

class TestCategoryService
{
    public const UNCATEGORIZED = 'Chưa phân loại';

    /**
     * Cây thư mục của một lớp (2 cấp) kèm tests_count từng nhánh.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(?int $classroomId): array
    {
        $roots = TestCategory::query()
            ->where('classroom_id', $classroomId)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount('tests')])
            ->withCount('tests')
            ->orderBy('order')
            ->get();

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
    public function sync(?int $classroomId, array $categories, array $deletedIds): array
    {
        return DB::transaction(function () use ($classroomId, $categories, $deletedIds) {
            $movedCount = 0;

            if ($deletedIds !== []) {
                $toDelete = TestCategory::query()
                    ->where('classroom_id', $classroomId)
                    ->whereIn('id', $deletedIds)
                    ->get();

                if ($toDelete->isNotEmpty()) {
                    $fallback = $this->uncategorized($classroomId);
                    $ids = $toDelete->pluck('id')->all();

                    // Đề trong thư mục bị xoá (và trong thư mục con của nó) → dồn về "Chưa phân loại".
                    $movedCount = \App\Models\Test::whereIn('category_id', $ids)->update(['category_id' => $fallback->id]);

                    // Thư mục con của thư mục bị xoá → đưa lên gốc.
                    TestCategory::whereIn('parent_id', $ids)->update(['parent_id' => null]);

                    TestCategory::whereIn('id', $ids)->where('id', '!=', $fallback->id)->delete();
                }
            }

            foreach ($categories as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $attrs = [
                    'name' => $name,
                    'classroom_id' => $classroomId,
                    'parent_id' => $row['parent_id'] ?? null,
                    'order' => (int) ($row['order'] ?? 0),
                ];

                if (! empty($row['id'])) {
                    TestCategory::where('id', $row['id'])->where('classroom_id', $classroomId)->update($attrs);
                } else {
                    TestCategory::create($attrs);
                }
            }

            return ['moved_count' => $movedCount];
        });
    }

    public function uncategorized(?int $classroomId): TestCategory
    {
        return TestCategory::firstOrCreate(
            ['classroom_id' => $classroomId, 'name' => self::UNCATEGORIZED, 'parent_id' => null],
            ['order' => 999],
        );
    }
}
