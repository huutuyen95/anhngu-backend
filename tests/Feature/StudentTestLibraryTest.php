<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Test;
use App\Models\TestCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Màn "Thư viện → Đề thi" của học viên: GET /api/v1/tests (StudentTestService).
 */
class StudentTestLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function makeTest(User $teacher, array $overrides = []): Test
    {
        $test = Test::create(array_merge([
            'created_by' => $teacher->id,
            'title' => 'Reading Practice',
            'description' => 'Luyện đọc hiểu 20 phút, trình độ A2.',
            'slug' => 'reading-practice',
            'skill' => 'reading',
            'duration_minutes' => 20,
            'total_score' => 10,
            'is_published' => true,
        ], $overrides));

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Chọn đáp án đúng']);
        $section->questions()->create(['order' => 1, 'type' => 'multiple_choice', 'content' => 'Q1', 'score' => 1]);
        $section->questions()->create(['order' => 2, 'type' => 'multiple_choice', 'content' => 'Q2', 'score' => 1]);

        return $test->fresh();
    }

    private function listEntry(User $student, Test $test): ?array
    {
        $response = $this->actingAs($student)->getJson('/api/v1/tests');
        $response->assertOk();

        return collect($response->json())->firstWhere('id', $test->id);
    }

    public function test_card_returns_description_and_question_count(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest($teacher);

        $entry = $this->listEntry($student, $test);

        $this->assertSame('Luyện đọc hiểu 20 phút, trình độ A2.', $entry['description']);
        $this->assertSame('reading', $entry['skill']);
        $this->assertSame(20, $entry['duration_minutes']);
        $this->assertSame(2, $entry['question_count']);
        $this->assertNull($entry['attempt']);
    }

    public function test_card_returns_category_path_with_classroom_and_parent(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => '6A1',
            'slug' => '6a1',
            'is_active' => true,
        ]);
        $root = TestCategory::create(['name' => 'Ngữ pháp', 'classroom_id' => $classroom->id, 'order' => 0]);
        $child = TestCategory::create([
            'name' => 'Unit 5',
            'classroom_id' => $classroom->id,
            'parent_id' => $root->id,
            'order' => 0,
        ]);

        $test = $this->makeTest($teacher, ['category_id' => $child->id]);

        $entry = $this->listEntry($student, $test);

        $this->assertSame($child->id, $entry['category']['id']);
        $this->assertSame('Unit 5', $entry['category']['name']);
        $this->assertSame('Ngữ pháp', $entry['category']['parent_name']);
        $this->assertSame('6A1', $entry['category']['classroom_name']);
        $this->assertSame('6A1 / Ngữ pháp / Unit 5', $entry['category']['path']);
    }

    public function test_shared_category_has_no_classroom_and_test_without_category_returns_null(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        // Thư mục dùng chung: classroom_id null, không có cha.
        $shared = TestCategory::create(['name' => 'Đề cô chia sẻ', 'order' => 0]);
        $inShared = $this->makeTest($teacher, ['category_id' => $shared->id]);
        $noCategory = $this->makeTest($teacher, ['title' => 'Không thư mục', 'slug' => 'khong-thu-muc']);

        $sharedEntry = $this->listEntry($student, $inShared);
        $this->assertNull($sharedEntry['category']['classroom_name']);
        $this->assertNull($sharedEntry['category']['parent_name']);
        $this->assertSame('Đề cô chia sẻ', $sharedEntry['category']['path']);

        $this->assertNull($this->listEntry($student, $noCategory)['category']);
    }

    public function test_unpublished_test_is_hidden_and_config_is_not_leaked(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $draft = $this->makeTest($teacher, ['title' => 'Nháp', 'slug' => 'nhap', 'is_published' => false]);
        $published = $this->makeTest($teacher);

        $this->assertNull($this->listEntry($student, $draft));

        $entry = $this->listEntry($student, $published);
        foreach (['is_published', 'rubric', 'scoring_method', 'shuffle_questions', 'ai_grading'] as $leaked) {
            $this->assertArrayNotHasKey($leaked, $entry);
        }
    }

    /** Số query không được tăng theo số đề (category/parent/classroom phải eager load). */
    public function test_listing_does_not_scale_queries_with_number_of_tests(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $classroom = Classroom::create([
            'teacher_id' => $teacher->id, 'name' => '6A1', 'slug' => '6a1', 'is_active' => true,
        ]);

        $countQueries = function () use ($student): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($student)->getJson('/api/v1/tests')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        foreach (range(1, 3) as $i) {
            $category = TestCategory::create(['name' => "Unit {$i}", 'classroom_id' => $classroom->id, 'order' => $i]);
            $this->makeTest($teacher, ['title' => "Đề {$i}", 'slug' => "de-{$i}", 'category_id' => $category->id]);
        }
        $withThree = $countQueries();

        foreach (range(4, 9) as $i) {
            $category = TestCategory::create(['name' => "Unit {$i}", 'classroom_id' => $classroom->id, 'order' => $i]);
            $this->makeTest($teacher, ['title' => "Đề {$i}", 'slug' => "de-{$i}", 'category_id' => $category->id]);
        }
        $withNine = $countQueries();

        $this->assertSame($withThree, $withNine, "3 đề tốn {$withThree} query, 9 đề tốn {$withNine} → có N+1.");
    }

    public function test_teacher_can_set_and_update_description(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $created = $this->actingAs($teacher)->postJson('/api/v1/admin/tests', [
            'title' => 'Đề mới',
            'skill' => 'listening',
            'description' => 'Nghe hội thoại ngắn.',
        ]);
        $created->assertCreated()->assertJsonPath('test.description', 'Nghe hội thoại ngắn.');

        $testId = $created->json('test.id');

        $this->actingAs($teacher)
            ->putJson("/api/v1/admin/tests/{$testId}", ['description' => 'Đã sửa mô tả.'])
            ->assertOk();

        $this->assertSame('Đã sửa mô tả.', Test::find($testId)->description);

        $this->actingAs($teacher)
            ->putJson("/api/v1/admin/tests/{$testId}", ['description' => str_repeat('a', 501)])
            ->assertJsonValidationErrors('description');
    }
}
