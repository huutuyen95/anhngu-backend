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
        // 2/5 thẻ đã thuộc — tiến độ TRONG LỚP (scope classroom_id).
        foreach ($cards->take(2) as $card) {
            CardProgress::create(['user_id' => $this->student->id, 'card_id' => $card->id, 'classroom_id' => $this->class->id, 'status' => 'known']);
        }
        // Tiến độ tự luyện Thư viện (classroom_id null) KHÔNG được tính vào lộ trình lớp.
        CardProgress::create(['user_id' => $this->student->id, 'card_id' => $cards[2]->id, 'classroom_id' => null, 'status' => 'known']);
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

    private function makeClass(string $name, ?string $starts, ?string $ends, User $for): Classroom
    {
        $c = Classroom::create([
            'teacher_id' => $this->teacher->id, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
            'is_active' => true, 'starts_on' => $starts, 'ends_on' => $ends,
        ]);
        $c->students()->attach($for->id, ['status' => 'studying']);

        return $c;
    }

    public function test_my_classrooms_sorted_active_upcoming_ended(): void
    {
        // Xoá lớp mặc định để chỉ còn 3 lớp trong bài test.
        $this->class->students()->detach($this->student->id);

        $this->makeClass('Lớp Ended 9C', now()->subMonths(3)->toDateString(), now()->subDay()->toDateString(), $this->student);
        $this->makeClass('Lớp Upcoming 7B', now()->addWeek()->toDateString(), now()->addMonths(2)->toDateString(), $this->student);
        $this->makeClass('Lớp Active 8A', now()->subMonth()->toDateString(), now()->addMonth()->toDateString(), $this->student);

        $res = $this->actingAs($this->student)->getJson('/api/v1/me/classrooms')->assertOk();
        $statuses = collect($res->json('data'))->pluck('status')->all();
        $this->assertSame(['active', 'upcoming', 'ended'], $statuses);
        // Mã lớp suy từ tên.
        $this->assertSame('8A', $res->json('data.0.code'));
    }

    public function test_ended_class_roadmap_still_readable(): void
    {
        $ended = $this->makeClass('Lớp cũ', now()->subMonths(2)->toDateString(), now()->subDay()->toDateString(), $this->student);
        $session = $ended->sessions()->create(['title' => 'Buổi 1', 'order' => 1, 'is_visible' => true]);
        $this->assignMission($session->id, $this->makeTest(), ['classroom_id' => $ended->id]);

        $res = $this->actingAs($this->student)->getJson("/api/v1/classrooms/{$ended->id}/roadmap")->assertOk();
        $this->assertSame('ended', $res->json('classroom.status'));
        $this->assertCount(1, $res->json('sessions.0.items'));
    }

    public function test_locked_future_session_returns_no_items(): void
    {
        $session = $this->class->sessions()->create([
            'title' => 'Buổi tương lai', 'order' => 1, 'is_visible' => true, 'held_on' => now()->addWeek()->toDateString(),
        ]);
        $this->assignMission($session->id, $this->makeTest());

        $res = $this->actingAs($this->student)->getJson("/api/v1/classrooms/{$this->class->id}/roadmap")->assertOk();
        $this->assertTrue($res->json('sessions.0.locked'));
        $this->assertSame([], $res->json('sessions.0.items'));
    }

    public function test_pending_review_attempt_excluded_from_avg(): void
    {
        $session = $this->class->sessions()->create(['title' => 'B1', 'order' => 1, 'is_visible' => true]);
        $graded = $this->makeTest();
        $pending = $this->makeTest('writing');
        $mGraded = $this->assignMission($session->id, $graded);
        $mPending = $this->assignMission($session->id, $pending);

        TestAttempt::create([
            'user_id' => $this->student->id, 'test_id' => $graded->id, 'classroom_id' => $this->class->id,
            'mission_id' => $mGraded->id, 'source' => 'assignment', 'status' => 'graded',
            'started_at' => now(), 'question_count' => 5, 'total_score' => 8.0,
        ]);
        // Lượt chờ chấm có điểm tạm nhưng KHÔNG được tính vào TB.
        TestAttempt::create([
            'user_id' => $this->student->id, 'test_id' => $pending->id, 'classroom_id' => $this->class->id,
            'mission_id' => $mPending->id, 'source' => 'assignment', 'status' => 'pending_review',
            'started_at' => now(), 'question_count' => 5, 'total_score' => 2.0,
        ]);

        $res = $this->actingAs($this->student)->getJson('/api/v1/me/classrooms')->assertOk();
        $this->assertEquals(8.0, $res->json('data.0.avg_score'));
    }

    public function test_midterm_student_progress_counts_only_own_missions(): void
    {
        // Học sinh vào lớp giữa kỳ chỉ nhận nhiệm vụ của mình → tiến độ theo mission riêng.
        $late = User::factory()->create();
        $this->class->students()->attach($late->id, ['status' => 'studying']);
        $session = $this->class->sessions()->create(['title' => 'B1', 'order' => 1, 'is_visible' => true]);

        $this->assignMissionFor($late, $session->id, $this->makeTest(), ['status' => 'done']);
        $this->assignMissionFor($late, $session->id, $this->makeTest(), ['status' => 'todo']);

        $res = $this->actingAs($late)->getJson('/api/v1/me/classrooms')->assertOk();
        $this->assertSame(2, $res->json('data.0.total_count'));
        $this->assertSame(1, $res->json('data.0.done_count'));
        $this->assertSame(50, $res->json('data.0.progress_pct'));
    }

    private function assignMissionFor(User $user, int $sessionId, $model, array $extra = []): Mission
    {
        return Mission::create(array_merge([
            'user_id' => $user->id, 'assigned_by' => $this->teacher->id,
            'classroom_id' => $this->class->id, 'class_session_id' => $sessionId,
            'missionable_type' => $model->getMorphClass(), 'missionable_id' => $model->id,
            'source' => 'suggested', 'status' => 'todo', 'attempts_allowed' => 1,
        ], $extra));
    }

    public function test_teacher_cannot_open_student_roadmap(): void
    {
        $this->actingAs($this->teacher)
            ->getJson("/api/v1/classrooms/{$this->class->id}/roadmap")
            ->assertForbidden();
    }
}
