<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Chốt các mặt của khu GIÁO VIÊN bị đụng khi làm màn đề thi cho học viên:
 * cột `tests.description` mới, `Question::countsByTest()` gộp từ 2 service, và
 * `tests.skill` chuyển enum → string (so sánh với App\Enums\Skill phải còn đúng).
 */
class AdminTestRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
    }

    private function makeTest(string $title, string $skill, int $questions = 2, array $overrides = []): Test
    {
        $test = Test::create(array_merge([
            'created_by' => $this->teacher->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'skill' => $skill,
            'duration_minutes' => 20,
            'total_score' => 10,
            'is_published' => true,
        ], $overrides));

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Làm bài']);

        foreach (range(1, $questions) as $i) {
            $section->questions()->create([
                'order' => $i,
                'type' => $skill === 'writing' ? 'writing' : 'multiple_choice',
                'content' => "Q{$i}",
                'score' => 1,
            ]);
        }

        return $test->fresh();
    }

    /** Cột skill giờ là varchar — lọc theo kỹ năng ở list giáo viên phải còn nguyên. */
    public function test_admin_list_filters_by_skill_and_counts_questions(): void
    {
        $this->makeTest('Đề đọc', 'reading', questions: 3);
        $this->makeTest('Đề nghe', 'listening', questions: 5);

        $response = $this->actingAs($this->teacher)->getJson('/api/v1/admin/tests?skill=reading');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Đề đọc', $response->json('data.0.title'));
        $this->assertSame('reading', $response->json('data.0.skill'));
        // question_count đi qua Question::countsByTest() sau khi gộp query trùng.
        $this->assertSame(3, $response->json('data.0.question_count'));

        $all = $this->actingAs($this->teacher)->getJson('/api/v1/admin/tests');
        $all->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(
            [3, 5],
            collect($all->json('data'))->pluck('question_count')->all(),
        );
    }

    public function test_admin_list_exposes_description(): void
    {
        $this->makeTest('Đề có mô tả', 'reading', overrides: ['description' => 'Mô tả ngắn.']);

        $response = $this->actingAs($this->teacher)->getJson('/api/v1/admin/tests');

        $response->assertOk()->assertJsonPath('data.0.description', 'Mô tả ngắn.');
    }

    public function test_duplicate_copies_description(): void
    {
        $test = $this->makeTest('Đề gốc', 'reading', overrides: ['description' => 'Mô tả gốc.']);

        $response = $this->actingAs($this->teacher)->postJson("/api/v1/admin/tests/{$test->id}/duplicate");

        $response->assertCreated();
        $this->assertSame('Mô tả gốc.', $response->json('test.description'));
        $this->assertNotSame($test->id, $response->json('test.id'));
    }

    /**
     * ContentController so sánh cột skill với App\Enums\Skill (`where('skill', Skill::Writing)`).
     * Sau khi cột thành varchar, so sánh này vẫn phải phân loại đúng.
     */
    public function test_assignable_content_still_splits_writing_from_other_tests(): void
    {
        $this->makeTest('Bài viết luận', 'writing');
        $this->makeTest('Đề đọc hiểu', 'reading');
        $this->makeTest('Đề tổng hợp', 'mixed');

        $writing = $this->actingAs($this->teacher)->getJson('/api/v1/assignable-content?type=writing');
        $writing->assertOk();
        $this->assertSame(['Bài viết luận'], collect($writing->json('data'))->pluck('title')->all());

        $tests = $this->actingAs($this->teacher)->getJson('/api/v1/assignable-content?type=test');
        $tests->assertOk();
        $this->assertEqualsCanonicalizing(
            ['Đề đọc hiểu', 'Đề tổng hợp'],
            collect($tests->json('data'))->pluck('title')->all(),
        );
    }

    /**
     * Khoá ngoại activity_logs.test_id là nullOnDelete — xoá đề KHÔNG được vướng khoá ngoại,
     * và log lịch sử vẫn còn (chỉ mất liên kết tới đề đã xoá).
     */
    public function test_deleting_a_test_with_activity_logs_succeeds(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $test = $this->makeTest('Đề sắp xoá', 'reading', questions: 1);

        ActivityLog::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'type' => 'test_attempt',
            'subject' => $test->title,
            'score' => 7.0,
            'created_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->deleteJson("/api/v1/admin/tests/{$test->id}")
            ->assertOk();

        $this->assertDatabaseMissing('tests', ['id' => $test->id]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $student->id, 'test_id' => null]);
    }

    /** Editor cấu trúc đề — dùng model Question vừa được thêm countsByTest(). */
    public function test_save_structure_round_trips(): void
    {
        $test = $this->makeTest('Đề sẽ sửa cấu trúc', 'reading', questions: 1);

        $response = $this->actingAs($this->teacher)->putJson("/api/v1/admin/tests/{$test->id}/structure", [
            'parts' => [[
                'order' => 0,
                'title' => 'Part 1',
                'sections' => [[
                    'order' => 0,
                    'instruction' => 'Chọn đáp án',
                    'questions' => [[
                        'order' => 0,
                        'type' => 'multiple_choice',
                        'content' => 'Câu mới',
                        'images' => [],
                        'record_limit_seconds' => null,
                        'options' => [
                            ['label' => 'A', 'content' => 'Đúng', 'is_correct' => true],
                            ['label' => 'B', 'content' => 'Sai', 'is_correct' => false],
                        ],
                    ]],
                ]],
            ]],
        ]);

        $response->assertOk();
        $this->assertSame('Câu mới', $response->json('test.parts.0.sections.0.questions.0.content'));

        // Số câu ở list phải cập nhật theo cấu trúc mới.
        $list = $this->actingAs($this->teacher)->getJson('/api/v1/admin/tests');
        $this->assertSame(1, $list->json('data.0.question_count'));
    }
}
