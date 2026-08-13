<?php

namespace Tests\Feature;

use App\Models\CardProgress;
use App\Models\Classroom;
use App\Models\Deck;
use App\Models\Document;
use App\Models\Mission;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentRoadmapTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Classroom $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->create();
        $this->class = Classroom::create([
            'teacher_id' => $this->teacher->id, 'name' => 'Lớp 6A', 'slug' => 'lop-6a', 'is_active' => true,
        ]);
        $this->class->students()->attach($this->student->id, ['status' => 'studying']);
    }

    private function makeTest(string $skill = 'reading'): Test
    {
        return Test::create([
            'created_by' => $this->teacher->id, 'title' => 'Đề', 'slug' => 'de-'.uniqid(),
            'skill' => $skill, 'duration_minutes' => 30, 'total_score' => 10, 'is_published' => true,
        ]);
    }

    private function assignMission(int $sessionId, $model, array $extra = []): Mission
    {
        return Mission::create(array_merge([
            'user_id' => $this->student->id, 'assigned_by' => $this->teacher->id,
            'classroom_id' => $this->class->id, 'class_session_id' => $sessionId,
            'missionable_type' => $model->getMorphClass(), 'missionable_id' => $model->id,
            'source' => 'suggested', 'status' => 'todo', 'attempts_allowed' => 1,
        ], $extra));
    }

    public function test_student_not_in_class_gets_403(): void
    {
        $other = User::factory()->create();
        $this->actingAs($other)->getJson("/api/v1/classrooms/{$this->class->id}/roadmap")->assertForbidden();
    }

    public function test_only_visible_sessions_are_returned(): void
    {
        $visible = $this->class->sessions()->create(['title' => 'Buổi 1', 'order' => 1, 'is_visible' => true]);
        $this->class->sessions()->create(['title' => 'Buổi ẩn', 'order' => 2, 'is_visible' => false]);
        $this->assignMission($visible->id, $this->makeTest());

        $res = $this->actingAs($this->student)->getJson("/api/v1/classrooms/{$this->class->id}/roadmap")->assertOk();

        $titles = collect($res->json('sessions'))->pluck('title');
        $this->assertContains('Buổi 1', $titles);
        $this->assertNotContains('Buổi ẩn', $titles);
    }

    public function test_session_without_content_is_locked(): void
    {
        $this->class->sessions()->create(['title' => 'Buổi rỗng', 'order' => 1, 'is_visible' => true]);

        $res = $this->actingAs($this->student)->getJson("/api/v1/classrooms/{$this->class->id}/roadmap")->assertOk();
        $this->assertTrue($res->json('sessions.0.locked'));
        $this->assertSame(0, $res->json('sessions.0.total'));
    }

    public function test_assigned_content_shows_even_when_unpublished(): void
    {
        $session = $this->class->sessions()->create(['title' => 'B1', 'order' => 1, 'is_visible' => true]);
        $doc = Document::create([
            'type' => 'document', 'title' => 'Tài liệu A', 'slug' => 'tl-a', 'is_published' => false, 'created_by' => $this->teacher->id,
        ]);
        $this->assignMission($session->id, $doc);

        $res = $this->actingAs($this->student)->getJson("/api/v1/classrooms/{$this->class->id}/roadmap")->assertOk();
        $this->assertSame('Tài liệu A', $res->json('sessions.0.items.0.title'));
        $this->assertSame('document', $res->json('sessions.0.items.0.type'));
    }

    public function test_in_progress_attempt_returns_attempt_id(): void
    {
        $session = $this->class->sessions()->create(['title' => 'B1', 'order' => 1, 'is_visible' => true]);
        $test = $this->makeTest();
        $mission = $this->assignMission($session->id, $test);
        // Lượt phải gắn mission thì mới là "bài cô giao" — lượt tự luyện cùng đề không tính.
        $attempt = TestAttempt::create([
            'user_id' => $this->student->id, 'test_id' => $test->id, 'classroom_id' => $this->class->id,
            'mission_id' => $mission->id, 'source' => 'assignment',
            'status' => 'in_progress', 'started_at' => now(), 'question_count' => 5,
        ]);

        $res = $this->actingAs($this->student)->getJson("/api/v1/classrooms/{$this->class->id}/roadmap")->assertOk();
        $item = $res->json('sessions.0.items.0');
        $this->assertSame('in_progress', $item['status']);
        $this->assertSame($attempt->id, $item['attempt_id']);
    }

    public function test_deck_progress_counts_known_over_total(): void
    {
        $session = $this->class->sessions()->create(['title' => 'B1', 'order' => 1, 'is_visible' => true]);
        $deck = Deck::create(['owner_id' => $this->teacher->id, 'name' => 'Bộ từ', 'slug' => 'bo-tu', 'is_public' => true, 'is_published' => true]);
        $cards = collect(range(1, 5))->map(fn ($i) => $deck->cards()->create(['term' => "w{$i}", 'meaning' => "n{$i}", 'order' => $i]));
        // 2/5 thẻ đã thuộc.
        foreach ($cards->take(2) as $card) {
            CardProgress::create(['user_id' => $this->student->id, 'card_id' => $card->id, 'status' => 'known']);
        }
        $this->assignMission($session->id, $deck);

        $res = $this->actingAs($this->student)->getJson("/api/v1/classrooms/{$this->class->id}/roadmap")->assertOk();
        $item = $res->json('sessions.0.items.0');
        $this->assertSame('deck', $item['type']);
        $this->assertSame(40, $item['progress_pct']); // 2/5
        $this->assertSame('in_progress', $item['status']);
    }

    public function test_my_classrooms_lists_student_classes(): void
    {
        $res = $this->actingAs($this->student)->getJson('/api/v1/me/classrooms')->assertOk();
        $this->assertSame('Lớp 6A', $res->json('data.0.name'));
        $this->assertSame($this->teacher->name, $res->json('data.0.teacher_name'));
    }
}
