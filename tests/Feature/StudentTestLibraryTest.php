<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Classroom;
use App\Models\Test;
use App\Models\TestCategory;
use App\Models\User;
use App\Services\SettingService;
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

    /** Đề writing → nộp xong sẽ ở `pending_review` (nhóm "Chờ cô chấm"). */
    private function makeWritingTest(User $teacher, array $overrides = []): Test
    {
        $test = Test::create(array_merge([
            'created_by' => $teacher->id,
            'title' => 'Writing — My Summer Holiday',
            'slug' => 'writing-summer',
            'skill' => 'writing',
            'duration_minutes' => 30,
            'total_score' => 10,
            'word_limit' => 150,
            'is_published' => true,
        ], $overrides));

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Viết đoạn văn']);
        $section->questions()->create(['order' => 1, 'type' => 'writing', 'content' => 'Describe.', 'score' => 10]);

        return $test->fresh();
    }

    private function questionsOf(Test $test)
    {
        return $test->load('parts.sections.questions')->parts->first()->sections->first()->questions;
    }

    /** Bắt đầu lượt làm và trả lời `$answered` câu đầu (không nộp) → attempt ở `in_progress`. */
    private function startAttempt(User $student, Test $test, int $answered = 0): int
    {
        $attemptId = $this->actingAs($student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');

        if ($answered > 0) {
            $answers = $this->questionsOf($test)->take($answered)
                ->map(fn ($q) => ['question_id' => $q->id, 'answer_text' => 'x'])
                ->values()->all();

            $this->actingAs($student)
                ->putJson("/api/v1/attempts/{$attemptId}/answers", ['answers' => $answers])
                ->assertOk();
        }

        return $attemptId;
    }

    private function submitAttempt(User $student, Test $test): int
    {
        $attemptId = $this->startAttempt($student, $test, 1);
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        return $attemptId;
    }

    private function listEntry(User $student, Test $test): ?array
    {
        $response = $this->actingAs($student)->getJson('/api/v1/tests');
        $response->assertOk();

        return collect($response->json('data'))->firstWhere('id', $test->id);
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

    public function test_card_returns_avg_score_and_total_attempts_from_activity_logs(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest($teacher);

        // Chỉ số của cả lớp: gồm cả lượt của bạn khác và lượt đã bị dedup xoá khỏi test_attempts.
        foreach ([[$student, 10.0], [$student, 5.0], [$other, 8.4]] as [$user, $score]) {
            ActivityLog::create([
                'user_id' => $user->id,
                'test_id' => $test->id,
                'type' => 'test_attempt',
                'subject' => $test->title,
                'score' => $score,
                'created_at' => now(),
            ]);
        }

        // Log của loại khác không được tính vào.
        ActivityLog::create([
            'user_id' => $student->id, 'test_id' => $test->id,
            'type' => 'deck_study', 'score' => 1.0, 'created_at' => now(),
        ]);

        $entry = $this->listEntry($student, $test);

        $this->assertSame(3, $entry['attempts_total']);
        $this->assertSame(7.8, $entry['avg_score']); // (10 + 5 + 8.4) / 3 = 7.8
    }

    public function test_card_has_null_stats_when_nobody_has_attempted(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest($teacher);

        $entry = $this->listEntry($student, $test);

        $this->assertSame(0, $entry['attempts_total']);
        $this->assertNull($entry['avg_score']);
    }

    public function test_card_returns_word_limit_and_created_at(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $writing = $this->makeWritingTest($teacher);

        $entry = $this->listEntry($student, $writing);

        $this->assertSame(150, $entry['word_limit']);
        $this->assertNotNull($entry['created_at']);
    }

    public function test_in_progress_attempt_returns_progress_and_attempt_id(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest($teacher);

        $attemptId = $this->startAttempt($student, $test, 1);

        $entry = $this->listEntry($student, $test);

        $this->assertSame('in_progress', $entry['attempt']['status']);
        $this->assertSame('doing', $entry['attempt']['bucket']);
        $this->assertSame($attemptId, $entry['attempt']['id']);
        $this->assertSame(1, $entry['attempt']['answered_count']);
        $this->assertSame(2, $entry['attempt']['question_count']);
        $this->assertNull($entry['attempt']['best_score']);
    }

    /** Làm lại đề đã nộp: card phải hiện "Đang làm" nhưng vẫn giữ điểm cao nhất cũ. */
    public function test_new_attempt_on_finished_test_shows_in_progress_but_keeps_best_score(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest($teacher);

        $this->submitAttempt($student, $test);
        $this->startAttempt($student, $test, 2);

        $entry = $this->listEntry($student, $test);

        $this->assertSame('in_progress', $entry['attempt']['status']);
        $this->assertSame('doing', $entry['attempt']['bucket']);
        $this->assertSame(2, $entry['attempt']['answered_count']);
        $this->assertNotNull($entry['attempt']['best_score']);
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

        // Nạp sẵn cache settings (middleware bảo trì đọc 1 lần, chi phí O(1) không phải N+1).
        app(SettingService::class)->all();

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
