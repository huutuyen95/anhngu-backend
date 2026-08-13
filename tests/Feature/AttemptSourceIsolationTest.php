<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Mission;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\ClassroomStatsService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tách nguồn làm bài: lượt TỰ LUYỆN ở Thư viện (`mission_id` null) và lượt BÀI GIAO trong lớp
 * (`mission_id` != null) phải hoàn toàn độc lập — không chia sẻ tiến trình, điểm, số lần làm,
 * và không xoá dữ liệu của nhau khi dedup lượt-điểm-cao-nhất.
 */
class AttemptSourceIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Classroom $class;

    private Test $test;

    private Question $question;

    private int $correctOptionId;

    private int $wrongOptionId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->create();

        $this->class = Classroom::create([
            'teacher_id' => $this->teacher->id, 'name' => 'Lớp 6A', 'slug' => 'lop-6a', 'is_active' => true,
        ]);
        $this->class->students()->attach($this->student->id, ['status' => 'studying']);

        $this->test = Test::create([
            'created_by' => $this->teacher->id, 'title' => 'Đề chung', 'slug' => 'de-chung',
            'skill' => 'reading', 'duration_minutes' => 30, 'total_score' => 10, 'is_published' => true,
        ]);

        $part = $this->test->parts()->create(['title' => 'Part 1', 'order' => 1]);
        $section = $part->sections()->create(['order' => 1]);
        $this->question = $section->questions()->create([
            'type' => 'multiple_choice', 'content' => '1 + 1 = ?', 'order' => 1, 'score' => 1,
        ]);
        $this->correctOptionId = $this->question->options()->create(['content' => '2', 'is_correct' => true, 'order' => 1])->id;
        $this->wrongOptionId = $this->question->options()->create(['content' => '3', 'is_correct' => false, 'order' => 2])->id;
    }

    private function assignTest(array $extra = []): Mission
    {
        $session = $this->class->sessions()->create(['title' => 'Buổi 1', 'order' => 1, 'is_visible' => true]);

        return Mission::create(array_merge([
            'user_id' => $this->student->id, 'assigned_by' => $this->teacher->id,
            'classroom_id' => $this->class->id, 'class_session_id' => $session->id,
            'missionable_type' => $this->test->getMorphClass(), 'missionable_id' => $this->test->id,
            'source' => 'suggested', 'status' => 'todo', 'attempts_allowed' => 1,
        ], $extra));
    }

    /** Mở lượt làm bài rồi nộp với đáp án đúng/sai. */
    private function attemptAndSubmit(bool $correct, ?int $missionId = null): int
    {
        $start = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts", array_filter(['mission_id' => $missionId]))
            ->assertOk();

        $attemptId = $start->json('attempt_id');

        $this->actingAs($this->student)->putJson("/api/v1/attempts/{$attemptId}/answers", [
            'answers' => [[
                'question_id' => $this->question->id,
                'question_option_id' => $correct ? $this->correctOptionId : $this->wrongOptionId,
            ]],
        ])->assertOk();

        $this->actingAs($this->student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        return $attemptId;
    }

    public function test_library_and_assignment_attempts_live_side_by_side(): void
    {
        $mission = $this->assignTest();

        $assigned = $this->attemptAndSubmit(correct: true, missionId: $mission->id);
        $library = $this->attemptAndSubmit(correct: false);

        // (a) Hai dòng attempt tồn tại song song, đúng nguồn.
        $this->assertDatabaseHas('test_attempts', [
            'id' => $assigned, 'mission_id' => $mission->id, 'source' => 'assignment',
            'classroom_id' => $this->class->id,
        ]);
        $this->assertDatabaseHas('test_attempts', [
            'id' => $library, 'mission_id' => null, 'source' => 'library', 'classroom_id' => null,
        ]);
        $this->assertSame(2, TestAttempt::where('user_id', $this->student->id)->count());
    }

    public function test_library_attempt_does_not_complete_the_class_mission(): void
    {
        $mission = $this->assignTest();

        $this->attemptAndSubmit(correct: true);

        // (b) Nhiệm vụ vẫn chưa làm.
        $this->assertSame('todo', $mission->fresh()->status);

        // (c) Tiến trình buổi học không nhúc nhích.
        $res = $this->actingAs($this->student)
            ->getJson("/api/v1/classrooms/{$this->class->id}/roadmap")->assertOk();

        $item = $res->json('sessions.0.items.0');
        $this->assertSame('todo', $item['status']);
        $this->assertNull($item['attempt_id']);
        $this->assertSame(0, $item['attempts_used']);
        $this->assertSame(0, $res->json('sessions.0.progress_pct'));
    }

    public function test_assignment_attempt_completes_mission_and_shows_on_roadmap(): void
    {
        $mission = $this->assignTest();

        $attemptId = $this->attemptAndSubmit(correct: true, missionId: $mission->id);

        $this->assertSame('done', $mission->fresh()->status);
        $this->assertNotNull($mission->fresh()->completed_at);

        $res = $this->actingAs($this->student)
            ->getJson("/api/v1/classrooms/{$this->class->id}/roadmap")->assertOk();

        $item = $res->json('sessions.0.items.0');
        $this->assertSame('graded', $item['status']);
        $this->assertSame($attemptId, $item['attempt_id']);
        $this->assertSame(10.0, (float) $item['score']);
        $this->assertSame(100, $res->json('sessions.0.progress_pct'));
    }

    public function test_class_reports_ignore_library_attempts(): void
    {
        $this->assignTest();

        // Chỉ tự luyện, điểm tuyệt đối — báo cáo lớp phải rỗng.
        $this->attemptAndSubmit(correct: true);

        $report = app(ReportService::class)->classReport($this->class->fresh(), '30d');

        // (d) Không lượt nào, không học viên hoạt động, không điểm.
        $this->assertSame(0, $report['stats']['attempts']);
        $this->assertSame(0, $report['stats']['active_students']);
        $this->assertSame(0, $report['stats']['completed']);
        $this->assertSame(0, $report['by_student'][0]['attempts']);
        $this->assertSame(0, $report['by_student'][0]['completion_pct']);

        $stats = app(ClassroomStatsService::class)->forClass($this->class->fresh());
        $this->assertSame(0.0, $stats['avg_score']);
        $this->assertSame(0, $stats['progress_pct']);
    }

    public function test_class_reports_count_assigned_attempts(): void
    {
        $mission = $this->assignTest();

        $this->attemptAndSubmit(correct: true, missionId: $mission->id);

        $report = app(ReportService::class)->classReport($this->class->fresh(), '30d');

        $this->assertSame(1, $report['stats']['attempts']);
        $this->assertSame(1, $report['stats']['active_students']);
        $this->assertSame(1, $report['stats']['completed']);
        $this->assertSame(100, $report['by_student'][0]['completion_pct']);

        $stats = app(ClassroomStatsService::class)->forClass($this->class->fresh());
        $this->assertSame(10.0, $stats['avg_score']);
        $this->assertSame(100, $stats['progress_pct']);
    }

    public function test_library_listing_hides_assignment_attempts(): void
    {
        $mission = $this->assignTest();

        $this->attemptAndSubmit(correct: true, missionId: $mission->id);

        // (e) Thư viện chỉ biết lượt tự luyện → đề vẫn "chưa làm", không có điểm.
        $res = $this->actingAs($this->student)->getJson('/api/v1/tests')->assertOk();

        $card = collect($res->json('data'))->firstWhere('id', $this->test->id);
        $this->assertNull($card['attempt']);

        $this->assertSame(1, $res->json('meta.status_counts.todo'));
        $this->assertSame(0, $res->json('meta.status_counts.done'));
    }

    public function test_low_scoring_library_attempt_does_not_delete_the_assigned_one(): void
    {
        $mission = $this->assignTest();

        $assigned = $this->attemptAndSubmit(correct: true, missionId: $mission->id);
        $this->attemptAndSubmit(correct: false);

        // (f) Bài cô giao 10đ còn nguyên, không bị dedup xoá bởi lượt tự luyện 0đ.
        $kept = TestAttempt::find($assigned);
        $this->assertNotNull($kept);
        $this->assertSame('10.00', $kept->total_score);
        $this->assertSame(1, $kept->answers()->count());
    }

    public function test_high_scoring_library_attempt_does_not_delete_the_assigned_one(): void
    {
        $mission = $this->assignTest(['attempts_allowed' => 1]);

        $assigned = $this->attemptAndSubmit(correct: false, missionId: $mission->id);
        $this->attemptAndSubmit(correct: true);

        // Chiều ngược lại: tự luyện điểm CAO hơn cũng không được nuốt mất bài giao điểm thấp.
        $kept = TestAttempt::find($assigned);
        $this->assertNotNull($kept);
        $this->assertSame('0.00', $kept->total_score);
    }

    public function test_starting_a_library_attempt_keeps_the_unfinished_assigned_one(): void
    {
        $mission = $this->assignTest();

        $assigned = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts", ['mission_id' => $mission->id])
            ->assertOk()->json('attempt_id');

        $this->actingAs($this->student)->putJson("/api/v1/attempts/{$assigned}/answers", [
            'answers' => [['question_id' => $this->question->id, 'question_option_id' => $this->correctOptionId]],
        ])->assertOk();

        // Mở đề đó ở Thư viện: KHÔNG được xoá bài đang làm dở của lớp.
        $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts")->assertOk();

        $this->assertDatabaseHas('test_attempts', ['id' => $assigned, 'status' => 'in_progress']);
        $this->assertSame(1, TestAttempt::find($assigned)->answers()->count());
    }

    public function test_library_practice_does_not_burn_assignment_attempts(): void
    {
        $mission = $this->assignTest(['attempts_allowed' => 1]);

        // Tự luyện 3 lần ở Thư viện.
        $this->attemptAndSubmit(correct: false);
        $this->attemptAndSubmit(correct: false);
        $this->attemptAndSubmit(correct: false);

        // Vẫn còn nguyên lượt của bài cô giao.
        $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts", ['mission_id' => $mission->id])
            ->assertOk();
    }

    public function test_assignment_attempts_allowed_is_enforced(): void
    {
        $mission = $this->assignTest(['attempts_allowed' => 1]);

        $this->attemptAndSubmit(correct: false, missionId: $mission->id);

        $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts", ['mission_id' => $mission->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mission_id');

        // Nhưng tự luyện ở Thư viện thì vẫn thoải mái.
        $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts")->assertOk();
    }

    public function test_mission_of_another_student_is_rejected(): void
    {
        $mission = $this->assignTest();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts", ['mission_id' => $mission->id])
            ->assertNotFound();
    }

    public function test_draft_and_scheduled_missions_cannot_be_started(): void
    {
        $draft = $this->assignTest(['status' => 'draft']);
        $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts", ['mission_id' => $draft->id])
            ->assertNotFound();

        $scheduled = $this->assignTest(['status' => 'scheduled', 'scheduled_at' => now()->addDay()]);
        $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts", ['mission_id' => $scheduled->id])
            ->assertStatus(422);
    }

    public function test_attempt_payloads_carry_the_origin_so_screens_can_differ(): void
    {
        $mission = $this->assignTest(['attempts_allowed' => 2, 'due_date' => '2026-09-01']);

        $assigned = $this->attemptAndSubmit(correct: true, missionId: $mission->id);
        $library = $this->attemptAndSubmit(correct: false);

        // Bài cô giao: đủ lớp/buổi/hạn nộp/số lượt để màn kết quả vẽ theo khu lớp học.
        $res = $this->actingAs($this->student)
            ->getJson("/api/v1/attempts/{$assigned}/result")->assertOk();

        $this->assertSame('assignment', $res->json('source'));
        $this->assertSame($mission->id, $res->json('mission.id'));
        $this->assertSame($this->class->id, $res->json('mission.classroom_id'));
        $this->assertSame('Lớp 6A', $res->json('mission.classroom_name'));
        $this->assertSame('Buổi 1', $res->json('mission.session_title'));
        $this->assertSame('2026-09-01', $res->json('mission.due_date'));
        $this->assertSame(2, $res->json('mission.attempts_allowed'));
        $this->assertSame(1, $res->json('mission.attempts_used'));

        // Tự luyện: không có mission → màn kết quả vẽ theo khu Thư viện.
        $res = $this->actingAs($this->student)
            ->getJson("/api/v1/attempts/{$library}/result")->assertOk();

        $this->assertSame('library', $res->json('source'));
        $this->assertNull($res->json('mission'));
    }

    public function test_in_progress_attempt_state_carries_the_origin(): void
    {
        $mission = $this->assignTest();

        $attemptId = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts", ['mission_id' => $mission->id])
            ->assertOk()->json('attempt_id');

        // Màn ĐANG làm bài cũng cần biết nguồn để hiện chip "Bài cô giao · <lớp>".
        $res = $this->actingAs($this->student)
            ->getJson("/api/v1/attempts/{$attemptId}")->assertOk();

        $this->assertSame('assignment', $res->json('source'));
        $this->assertSame('Lớp 6A', $res->json('mission.classroom_name'));
    }

    public function test_teacher_results_grid_exposes_the_source(): void
    {
        $mission = $this->assignTest();

        $this->attemptAndSubmit(correct: true, missionId: $mission->id);
        $this->attemptAndSubmit(correct: false);

        $all = $this->actingAs($this->teacher)
            ->getJson('/api/v1/admin/attempts?status=submitted')->assertOk();
        $this->assertCount(2, $all->json('data'));

        $assigned = $this->actingAs($this->teacher)
            ->getJson('/api/v1/admin/attempts?status=submitted&source=assignment')->assertOk();
        $this->assertCount(1, $assigned->json('data'));
        $this->assertSame('assignment', $assigned->json('data.0.source'));
        $this->assertSame('Lớp 6A', $assigned->json('data.0.classroom.name'));

        $library = $this->actingAs($this->teacher)
            ->getJson('/api/v1/admin/attempts?status=submitted&source=library')->assertOk();
        $this->assertCount(1, $library->json('data'));
        $this->assertSame('library', $library->json('data.0.source'));
        $this->assertNull($library->json('data.0.classroom'));
    }
}
