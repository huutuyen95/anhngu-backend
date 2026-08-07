<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Lọc / sắp xếp / phân trang + số đếm ở sidebar cho "Thư viện → Đề thi" (GET /api/v1/tests).
 */
class StudentTestFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
    }

    private function makeTest(string $title, array $overrides = []): Test
    {
        $test = Test::create(array_merge([
            'created_by' => $this->teacher->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'skill' => 'reading',
            'duration_minutes' => 20,
            'total_score' => 10,
            'is_published' => true,
        ], $overrides));

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Làm bài']);
        $section->questions()->create(['order' => 1, 'type' => 'multiple_choice', 'content' => 'Q1', 'score' => 10]);

        return $test->fresh();
    }

    private function makeWritingTest(string $title): Test
    {
        $test = Test::create([
            'created_by' => $this->teacher->id,
            'title' => $title,
            'slug' => Str::slug($title),
            'skill' => 'writing',
            'duration_minutes' => 30,
            'total_score' => 10,
            'is_published' => true,
        ]);

        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1, 'instruction' => 'Viết']);
        $section->questions()->create(['order' => 1, 'type' => 'writing', 'content' => 'Describe.', 'score' => 10]);

        return $test->fresh();
    }

    private function startAttempt(Test $test): int
    {
        $attemptId = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$test->id}/attempts")
            ->json('attempt_id');

        $question = $test->load('parts.sections.questions')->parts->first()->sections->first()->questions->first();

        $this->actingAs($this->student)->putJson("/api/v1/attempts/{$attemptId}/answers", [
            'answers' => [['question_id' => $question->id, 'answer_text' => 'x']],
        ])->assertOk();

        return $attemptId;
    }

    private function submitAttempt(Test $test): int
    {
        $attemptId = $this->startAttempt($test);
        $this->actingAs($this->student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        return $attemptId;
    }

    /** @return array<int, string> tiêu đề các đề trả về, theo đúng thứ tự */
    private function titles(array $query = []): array
    {
        $response = $this->actingAs($this->student)->getJson('/api/v1/tests?'.http_build_query($query));
        $response->assertOk();

        return collect($response->json('data'))->pluck('title')->all();
    }

    private function meta(array $query = []): array
    {
        return $this->actingAs($this->student)
            ->getJson('/api/v1/tests?'.http_build_query($query))
            ->assertOk()
            ->json('meta');
    }

    public function test_filters_by_title_and_skill(): void
    {
        $this->makeTest('Unit 5 — Present Perfect');
        $this->makeTest('Mini Test — Daily Routines', ['skill' => 'listening']);
        $this->makeWritingTest('Writing — My Summer Holiday');

        $this->assertSame(['Unit 5 — Present Perfect'], $this->titles(['q' => 'present']));
        $this->assertSame(['Mini Test — Daily Routines'], $this->titles(['skill' => 'listening']));
        $this->assertSame([], $this->titles(['q' => 'không có đề nào tên vậy']));
    }

    public function test_filters_by_status_bucket(): void
    {
        $todo = $this->makeTest('Chưa làm');
        $doing = $this->makeTest('Đang làm');
        $done = $this->makeTest('Đã làm');
        $grading = $this->makeWritingTest('Chờ chấm');

        $this->startAttempt($doing);
        $this->submitAttempt($done);
        $this->submitAttempt($grading);

        $this->assertSame([$todo->title], $this->titles(['status' => 'todo']));
        $this->assertSame([$doing->title], $this->titles(['status' => 'doing']));
        $this->assertSame([$done->title], $this->titles(['status' => 'done']));
        $this->assertSame([$grading->title], $this->titles(['status' => 'grading']));

        // Chọn nhiều nhóm cùng lúc (checkbox ở sidebar).
        $this->assertEqualsCanonicalizing(
            [$doing->title, $grading->title],
            $this->titles(['status' => 'doing,grading']),
        );

        // Giá trị rác bị bỏ qua → không lọc gì.
        $this->assertCount(4, $this->titles(['status' => 'linh-tinh']));
    }

    /** Đề đang làm dở chỉ thuộc nhóm "Đang làm", không lọt vào "Đã làm"/"Chờ chấm". */
    public function test_status_buckets_are_mutually_exclusive(): void
    {
        $test = $this->makeTest('Làm lại lần hai');
        $this->submitAttempt($test);
        $this->startAttempt($test);

        $this->assertSame([$test->title], $this->titles(['status' => 'doing']));
        $this->assertSame([], $this->titles(['status' => 'done']));
        $this->assertSame([], $this->titles(['status' => 'todo']));
    }

    public function test_sorting(): void
    {
        $old = $this->makeTest('A cũ');
        $mid = $this->makeTest('C giữa');
        $new = $this->makeTest('B mới');

        $old->forceFill(['created_at' => now()->subMonth()])->save();
        $mid->forceFill(['created_at' => now()->subDays(2)])->save();
        $new->forceFill(['created_at' => now()])->save();

        foreach (range(1, 3) as $i) {
            ActivityLog::create([
                'user_id' => $this->student->id, 'test_id' => $mid->id,
                'type' => 'test_attempt', 'score' => 5, 'created_at' => now(),
            ]);
        }

        $this->assertSame(['B mới', 'C giữa', 'A cũ'], $this->titles(['sort' => 'newest']));
        $this->assertSame(['A cũ', 'B mới', 'C giữa'], $this->titles(['sort' => 'name']));
        $this->assertSame('C giữa', $this->titles(['sort' => 'popular'])[0]);

        // sort không hợp lệ → rơi về mặc định "mới nhất".
        $this->assertSame($this->titles(['sort' => 'newest']), $this->titles(['sort' => 'bậy bạ']));
    }

    public function test_pagination(): void
    {
        foreach (range(1, 7) as $i) {
            $this->makeTest("Đề {$i}");
        }

        $first = $this->meta(['per_page' => 3]);
        $this->assertSame(1, $first['current_page']);
        $this->assertSame(3, $first['per_page']);
        $this->assertSame(3, $first['last_page']);
        $this->assertSame(7, $first['total']);
        $this->assertCount(3, $this->titles(['per_page' => 3]));
        $this->assertCount(1, $this->titles(['per_page' => 3, 'page' => 3]));

        // Không lấy trang trùng nhau.
        $this->assertEmpty(array_intersect(
            $this->titles(['per_page' => 3]),
            $this->titles(['per_page' => 3, 'page' => 2]),
        ));

        // per_page bị kẹp trần, không cho client kéo cả bảng.
        $this->assertSame(50, $this->meta(['per_page' => 9999])['per_page']);
    }

    public function test_meta_status_counts_and_new_this_week(): void
    {
        $this->makeTest('Chưa làm 1');
        $this->makeTest('Chưa làm 2');
        $doing = $this->makeTest('Đang làm');
        $done = $this->makeTest('Đã làm');
        $grading = $this->makeWritingTest('Chờ chấm');
        $old = $this->makeTest('Đề cũ');
        $old->forceFill(['created_at' => now()->subMonth()])->save();

        $this->startAttempt($doing);
        $this->submitAttempt($done);
        $this->submitAttempt($grading);

        $meta = $this->meta();

        $this->assertSame(6, $meta['total']);
        $this->assertSame(5, $meta['new_this_week']);
        $this->assertSame(
            ['todo' => 3, 'doing' => 1, 'done' => 1, 'grading' => 1],
            $meta['status_counts'],
        );
    }

    /** Badge đếm phải giữ nguyên khi đang lọc theo trạng thái, nhưng theo `q`/`skill`. */
    public function test_status_counts_ignore_status_filter_but_respect_search(): void
    {
        $doing = $this->makeTest('Unit 5 đang làm');
        $this->makeTest('Unit 5 chưa làm');
        $this->makeTest('Đề khác hẳn');

        $this->startAttempt($doing);

        $withStatusFilter = $this->meta(['status' => 'doing'])['status_counts'];
        $this->assertSame(['todo' => 2, 'doing' => 1, 'done' => 0, 'grading' => 0], $withStatusFilter);

        $searched = $this->meta(['q' => 'Unit 5'])['status_counts'];
        $this->assertSame(['todo' => 1, 'doing' => 1, 'done' => 0, 'grading' => 0], $searched);
    }
}
