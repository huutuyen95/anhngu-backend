<?php

namespace Tests\Feature;

use App\Enums\Skill;
use App\Models\Classroom;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestCategoryAndActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Classroom $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->class = Classroom::create([
            'teacher_id' => $this->teacher->id,
            'name' => 'Lớp 6A1',
            'slug' => 'lop-6a1',
            'is_active' => true,
        ]);
    }

    private function makeTest(array $overrides = []): Test
    {
        return Test::create(array_merge([
            'created_by' => $this->teacher->id,
            'title' => 'Đề '.uniqid(),
            'slug' => 'de-'.uniqid(),
            'skill' => Skill::Reading,
            'duration_minutes' => 30,
            'total_score' => 10,
            'is_published' => true,
        ], $overrides));
    }

    private function makeStructuredTest(): Test
    {
        $test = $this->makeTest(['skill' => Skill::Listening]);
        $part = $test->parts()->create(['order' => 1, 'title' => 'Part 1']);
        $section = $part->sections()->create(['order' => 1, 'audio_url' => 'http://x/a.mp3', 'max_plays' => 2]);
        $q = $section->questions()->create(['order' => 1, 'type' => 'multiple_choice', 'content' => 'Q1', 'explanation' => 'vì vậy', 'score' => 1]);
        $q->options()->create(['label' => 'A', 'content' => 'a', 'is_correct' => true]);
        $q->options()->create(['label' => 'B', 'content' => 'b', 'is_correct' => false]);

        return $test;
    }

    public function test_sync_creates_updates_deletes_and_reorders_in_one_request(): void
    {
        $keep = TestCategory::create(['name' => 'Grammar', 'group' => 'exam', 'order' => 1]);
        $drop = TestCategory::create(['name' => 'Bỏ', 'group' => 'exam', 'order' => 2]);

        $this->actingAs($this->teacher)->putJson('/api/v1/admin/test-categories/sync', [
            'group' => 'exam',
            'categories' => [
                ['id' => $keep->id, 'name' => 'Ngữ pháp', 'order' => 2],
                ['id' => null, 'name' => 'Nghe', 'order' => 1],
            ],
            'deleted_ids' => [$drop->id],
        ])->assertOk()->assertJsonPath('moved_count', 0);

        $this->assertDatabaseHas('test_categories', ['id' => $keep->id, 'name' => 'Ngữ pháp', 'order' => 2]);
        $this->assertDatabaseHas('test_categories', ['name' => 'Nghe', 'group' => 'exam']);
        $this->assertDatabaseMissing('test_categories', ['id' => $drop->id]);
    }

    public function test_deleting_category_with_tests_moves_them_to_uncategorized(): void
    {
        $cat = TestCategory::create(['name' => 'Ôn tập', 'group' => 'exercise', 'order' => 1]);
        $t1 = $this->makeTest(['category_id' => $cat->id]);
        $t2 = $this->makeTest(['category_id' => $cat->id]);

        $this->actingAs($this->teacher)->putJson('/api/v1/admin/test-categories/sync', [
            'group' => 'exercise',
            'categories' => [],
            'deleted_ids' => [$cat->id],
        ])->assertOk()->assertJsonPath('moved_count', 2);

        $fallback = TestCategory::where('group', 'exercise')->where('name', 'Chưa phân loại')->first();
        $this->assertNotNull($fallback);
        $this->assertEquals($fallback->id, $t1->fresh()->category_id);
        $this->assertEquals($fallback->id, $t2->fresh()->category_id);
    }

    public function test_deleting_test_with_attempts_is_blocked_without_force(): void
    {
        $test = $this->makeTest();
        $student = User::factory()->create();
        TestAttempt::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'started_at' => now(),
            'status' => 'submitted',
        ]);

        $this->actingAs($this->teacher)->deleteJson("/api/v1/admin/tests/{$test->id}")
            ->assertStatus(409)
            ->assertJsonPath('attempts_count', 1);
        $this->assertDatabaseHas('tests', ['id' => $test->id]);

        $this->actingAs($this->teacher)->deleteJson("/api/v1/admin/tests/{$test->id}?force=1")
            ->assertOk();
        $this->assertDatabaseMissing('tests', ['id' => $test->id]);
    }

    public function test_duplicate_clones_whole_structure(): void
    {
        $test = $this->makeStructuredTest();

        $res = $this->actingAs($this->teacher)->postJson("/api/v1/admin/tests/{$test->id}/duplicate")
            ->assertCreated()->json('test');

        $copy = Test::find($res['id']);
        $this->assertNotEquals($test->id, $copy->id);
        $this->assertFalse((bool) $copy->is_published);
        $this->assertEquals(1, $copy->parts()->count());
        $this->assertEquals(1, $copy->questionCount());
    }

    public function test_lists_tests_filtered_by_format(): void
    {
        $this->makeTest(['title' => 'Chuẩn A', 'format' => 'standard']);
        $this->makeTest(['title' => 'Chuẩn B', 'format' => 'standard']);
        $this->makeTest(['title' => 'IELTS Sim', 'format' => 'ielts_simulation']);

        $res = $this->actingAs($this->teacher)->getJson('/api/v1/admin/tests?format=ielts_simulation')->assertOk();
        $titles = collect($res->json('data'))->pluck('title');
        $this->assertContains('IELTS Sim', $titles);
        $this->assertNotContains('Chuẩn A', $titles);
        $this->assertSame('ielts_simulation', collect($res->json('data'))->firstWhere('title', 'IELTS Sim')['format']);
    }

    public function test_move_category(): void
    {
        $cat = TestCategory::create(['name' => 'Skills', 'group' => 'exam', 'order' => 1]);
        $test = $this->makeTest();

        $this->actingAs($this->teacher)->patchJson("/api/v1/admin/tests/{$test->id}/category", ['category_id' => $cat->id])
            ->assertOk()->assertJsonPath('test.category_id', $cat->id);
    }

    public function test_preflight_reports_checks(): void
    {
        $test = $this->makeStructuredTest();

        $this->actingAs($this->teacher)->getJson("/api/v1/admin/tests/{$test->id}/preflight")
            ->assertOk()
            ->assertJsonStructure(['checks' => [['key', 'ok', 'label', 'hint']], 'all_ok', 'question_count', 'has_listening']);
    }

    public function test_student_cannot_access_admin_test_categories(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->getJson('/api/v1/admin/test-categories')->assertForbidden();
    }
}
