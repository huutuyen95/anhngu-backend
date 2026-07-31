<?php

namespace Tests\Feature;

use App\Enums\Skill;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Mission;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Classroom $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->class = Classroom::create([
            'teacher_id' => $this->teacher->id, 'name' => 'Lớp Sync', 'slug' => 'lop-sync', 'is_active' => true,
        ]);
    }

    private function url(): string
    {
        return "/api/v1/classrooms/{$this->class->id}/sessions/sync";
    }

    public function test_sync_reorders_sessions(): void
    {
        $ids = [];
        foreach (range(1, 5) as $i) {
            $ids[] = $this->class->sessions()->create(['title' => "Buổi {$i}", 'order' => $i])->id;
        }
        // Đảo ngược thứ tự.
        $payload = ['sessions' => array_map(fn ($id, $idx) => ['id' => $id, 'title' => "Buổi ".(5 - $idx), 'is_visible' => true], array_reverse($ids), array_keys($ids)), 'deleted_ids' => []];

        $this->actingAs($this->teacher)->putJson($this->url(), $payload)->assertOk();

        // Buổi cuối cùng (id đầu tiên trong reversed) giờ order = 1.
        $this->assertEquals(1, ClassSession::find($ids[4])->order);
        $this->assertEquals(5, ClassSession::find($ids[0])->order);
    }

    public function test_sync_creates_updates_and_deletes_in_one_request(): void
    {
        $keep = $this->class->sessions()->create(['title' => 'Giữ', 'order' => 1]);
        $del = $this->class->sessions()->create(['title' => 'Xoá', 'order' => 2]);

        $this->actingAs($this->teacher)->putJson($this->url(), [
            'sessions' => [
                ['id' => $keep->id, 'title' => 'Giữ (đã sửa)', 'is_visible' => false],
                ['id' => null, 'title' => 'Buổi mới', 'is_visible' => true],
            ],
            'deleted_ids' => [$del->id],
        ])->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('deleted', 1);

        $this->assertDatabaseMissing('class_sessions', ['id' => $del->id]);
        $this->assertDatabaseHas('class_sessions', ['id' => $keep->id, 'title' => 'Giữ (đã sửa)', 'is_visible' => false]);
        $this->assertEquals(2, $this->class->sessions()->count());
    }

    public function test_delete_session_with_content_is_blocked_without_force(): void
    {
        $s = $this->class->sessions()->create(['title' => 'Có bài', 'order' => 1]);
        $student = User::factory()->create();
        $this->class->students()->attach($student->id, ['status' => 'studying']);
        $test = Test::create(['created_by' => $this->teacher->id, 'title' => 'T', 'slug' => 't1', 'skill' => Skill::Reading, 'duration_minutes' => 10, 'total_score' => 10, 'is_published' => true]);
        Mission::create([
            'user_id' => $student->id, 'classroom_id' => $this->class->id, 'class_session_id' => $s->id,
            'missionable_type' => $test->getMorphClass(), 'missionable_id' => $test->id, 'status' => 'todo',
        ]);

        $this->actingAs($this->teacher)->putJson($this->url(), [
            'sessions' => [], 'deleted_ids' => [$s->id],
        ])->assertStatus(409)->assertJsonPath('code', 'delete_blocked')->assertJsonPath('blocked.0.missions_count', 1);

        // DB không đổi.
        $this->assertDatabaseHas('class_sessions', ['id' => $s->id]);

        // Có force → xoá được.
        $this->actingAs($this->teacher)->putJson($this->url(), [
            'sessions' => [], 'deleted_ids' => [$s->id], 'force_delete_ids' => [$s->id],
        ])->assertOk();
        $this->assertDatabaseMissing('class_sessions', ['id' => $s->id]);
    }

    public function test_duplicate_titles_rejected_at_index(): void
    {
        $this->actingAs($this->teacher)->putJson($this->url(), [
            'sessions' => [
                ['id' => null, 'title' => 'Trùng'],
                ['id' => null, 'title' => 'Khác'],
                ['id' => null, 'title' => 'Trùng'],
            ],
            'deleted_ids' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['sessions.2.title']);
    }

    public function test_student_cannot_sync(): void
    {
        $this->actingAs(User::factory()->create())
            ->putJson($this->url(), ['sessions' => [], 'deleted_ids' => []])
            ->assertStatus(403);
    }
}
