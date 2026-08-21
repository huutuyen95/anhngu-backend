<?php

namespace Database\Seeders;

use App\Models\TestCategory;
use Illuminate\Database\Seeder;

/**
 * Danh mục đề mẫu theo 2 NHÓM nội dung (giống hệ cũ). Idempotent.
 */
class TestCategoryGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            TestCategory::GROUP_EXAM => ['Đề IELTS', 'Đề TOEIC', 'Cambridge'],
            TestCategory::GROUP_EXERCISE => ['Bài tập về nhà', 'Bài tập bổ trợ'],
        ];

        foreach ($groups as $group => $names) {
            foreach ($names as $i => $name) {
                TestCategory::firstOrCreate(
                    ['group' => $group, 'name' => $name, 'parent_id' => null],
                    ['order' => $i, 'classroom_id' => null],
                );
            }
        }
    }
}
