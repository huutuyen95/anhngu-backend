<?php

namespace App\Repositories;

use App\Models\Test;
use App\Models\TestCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TestCategoryRepository
{
    public function roots(?int $classroomId): Collection
    {
        return TestCategory::query()
            ->where('classroom_id', $classroomId)
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->withCount('tests')])
            ->withCount('tests')->orderBy('order')->get();
    }

    public function sync(?int $classroomId, array $categories, array $deletedIds): int
    {
        return DB::transaction(function () use ($classroomId, $categories, $deletedIds) {
            $movedCount = 0;
            if ($deletedIds !== []) {
                $toDelete = TestCategory::query()->where('classroom_id', $classroomId)->whereIn('id', $deletedIds)->get();
                if ($toDelete->isNotEmpty()) {
                    $fallback = $this->uncategorized($classroomId);
                    $ids = $toDelete->pluck('id')->all();
                    $movedCount = Test::whereIn('category_id', $ids)->update(['category_id' => $fallback->id]);
                    TestCategory::whereIn('parent_id', $ids)->update(['parent_id' => null]);
                    TestCategory::whereIn('id', $ids)->whereKeyNot($fallback->id)->delete();
                }
            }
            foreach ($categories as $row) {
                $attrs = [
                    'name' => $row['name'],
                    'classroom_id' => $classroomId,
                    'parent_id' => $row['parent_id'],
                    'order' => $row['order'],
                ];
                if ($row['id'] !== null) {
                    TestCategory::whereKey($row['id'])->where('classroom_id', $classroomId)->update($attrs);
                } else {
                    TestCategory::create($attrs);
                }
            }

            return $movedCount;
        });
    }

    public function uncategorized(?int $classroomId): TestCategory
    {
        return TestCategory::firstOrCreate(
            ['classroom_id' => $classroomId, 'name' => 'Chưa phân loại', 'parent_id' => null],
            ['order' => 999],
        );
    }
}
