<?php

namespace App\Repositories;

use App\Models\Test;
use App\Models\TestCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TestCategoryRepository
{
    /** Thư mục gốc của một NHÓM (exam|exercise) kèm số đề từng nhánh. */
    public function roots(string $group): Collection
    {
        return TestCategory::query()
            ->where('group', $group)
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->withCount('tests')])
            ->withCount('tests')->orderBy('order')->get();
    }

    public function sync(string $group, array $categories, array $deletedIds): int
    {
        return DB::transaction(function () use ($group, $categories, $deletedIds) {
            $movedCount = 0;
            if ($deletedIds !== []) {
                $toDelete = TestCategory::query()->where('group', $group)->whereIn('id', $deletedIds)->get();
                if ($toDelete->isNotEmpty()) {
                    $fallback = $this->uncategorized($group);
                    $ids = $toDelete->pluck('id')->all();
                    $movedCount = Test::whereIn('category_id', $ids)->update(['category_id' => $fallback->id]);
                    TestCategory::whereIn('parent_id', $ids)->update(['parent_id' => null]);
                    TestCategory::whereIn('id', $ids)->whereKeyNot($fallback->id)->delete();
                }
            }
            foreach ($categories as $row) {
                $attrs = [
                    'name' => $row['name'],
                    'group' => $group,
                    'classroom_id' => null,
                    'parent_id' => $row['parent_id'],
                    'order' => $row['order'],
                ];
                if ($row['id'] !== null) {
                    TestCategory::whereKey($row['id'])->where('group', $group)->update($attrs);
                } else {
                    TestCategory::create($attrs);
                }
            }

            return $movedCount;
        });
    }

    public function uncategorized(string $group): TestCategory
    {
        return TestCategory::firstOrCreate(
            ['group' => $group, 'name' => 'Chưa phân loại', 'parent_id' => null],
            ['order' => 999, 'classroom_id' => null],
        );
    }
}
