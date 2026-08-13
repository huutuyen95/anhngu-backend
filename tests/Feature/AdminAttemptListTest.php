<?php

namespace Tests\Feature;

use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Màn "Kết quả làm bài" khu giáo viên: tab "Tất cả" (không gửi `status`) phải ra mọi lượt,
 * tab "Chờ chấm" (gửi `status=pending_review`) mới lọc theo trạng thái.
 */
class AdminAttemptListTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Test $test;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->test = Test::create([
            'created_by' => $this->teacher->id,
            'title' => 'Reading Sample',
            'slug' => 'reading-sample',
            'skill' => 'reading',
            'duration_minutes' => 30,
            'total_score' => 10,
            'is_published' => true,
        ]);
    }

    private function makeAttempt(string $status): TestAttempt
    {
        return TestAttempt::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'test_id' => $this->test->id,
            'source' => 'library',
            'started_at' => now()->subHour(),
            'submitted_at' => $status === 'in_progress' ? null : now(),
            'question_count' => 1,
            'status' => $status,
        ]);
    }

    public function test_all_tab_without_status_returns_attempts_of_every_status(): void
    {
        $expected = collect(['in_progress', 'pending_review', 'submitted', 'graded'])
            ->mapWithKeys(fn ($status) => [$status => $this->makeAttempt($status)->id]);

        $response = $this->actingAs($this->teacher)->getJson('/api/v1/admin/attempts');

        $response->assertOk();
        $this->assertSame(4, $response->json('meta.total'));
        $this->assertEqualsCanonicalizing(
            $expected->values()->all(),
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    public function test_pending_review_tab_filters_by_status(): void
    {
        $this->makeAttempt('submitted');
        $this->makeAttempt('graded');
        $pending = $this->makeAttempt('pending_review');

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/admin/attempts?status=pending_review');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($pending->id, $response->json('data.0.id'));
        $this->assertSame('pending_review', $response->json('data.0.status'));
    }

    /** Bộ lọc khác vẫn chạy khi không có `status` (tab "Tất cả" + chọn nguồn). */
    public function test_source_filter_still_applies_without_status(): void
    {
        $this->makeAttempt('submitted');
        $assigned = $this->makeAttempt('graded');
        $assigned->update(['source' => 'assignment']);

        $response = $this->actingAs($this->teacher)
            ->getJson('/api/v1/admin/attempts?source=assignment');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($assigned->id, $response->json('data.0.id'));
    }
}
