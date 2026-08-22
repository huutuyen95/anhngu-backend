<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Test;
use App\Models\User;
use App\Notifications\AttemptGraded;
use App\Notifications\MissionAssigned;
use App\Services\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->create();
    }

    private function notifyStudent(int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->student->notify(new MissionAssigned(1, 'Lớp A', 2, null, 'Cô Uyên'));
        }
    }

    public function test_lists_notifications_and_unread_count(): void
    {
        $this->notifyStudent(3);

        $this->actingAs($this->student)->getJson('/api/v1/me/notifications')
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.kind', 'mission');

        $this->actingAs($this->student)->getJson('/api/v1/me/notifications/unread-count')
            ->assertOk()->assertJsonPath('count', 3);
    }

    public function test_filter_unread_only(): void
    {
        $this->notifyStudent(2);
        $first = $this->student->notifications()->first();
        $first->markAsRead();

        $this->actingAs($this->student)->getJson('/api/v1/me/notifications?filter=unread')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_mark_one_and_all_read(): void
    {
        $this->notifyStudent(3);
        $id = $this->student->notifications()->first()->id;

        $this->actingAs($this->student)->postJson("/api/v1/me/notifications/{$id}/read")->assertOk();
        $this->assertSame(2, $this->student->fresh()->unreadNotifications()->count());

        $this->actingAs($this->student)->postJson('/api/v1/me/notifications/read-all')
            ->assertOk()->assertJsonPath('updated', 2);
        $this->assertSame(0, $this->student->fresh()->unreadNotifications()->count());
    }

    public function test_cannot_read_another_students_notification(): void
    {
        $this->notifyStudent(1);
        $id = $this->student->notifications()->first()->id;
        $other = User::factory()->create();

        $this->actingAs($other)->postJson("/api/v1/me/notifications/{$id}/read")->assertNotFound();
        $this->assertNull($this->student->fresh()->notifications()->first()->read_at);
    }

    public function test_assigning_content_notifies_students(): void
    {
        Notification::fake();

        $class = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'Lớp A', 'slug' => 'lop-a', 'is_active' => true]);
        $class->students()->attach($this->student->id, ['status' => 'studying']);
        $session = $class->sessions()->create(['title' => 'Buổi 1', 'order' => 1, 'is_visible' => true]);
        $test = Test::create(['created_by' => $this->teacher->id, 'title' => 'Đề', 'slug' => 'de-'.uniqid(),
            'skill' => 'reading', 'duration_minutes' => 30, 'total_score' => 10, 'is_published' => true]);

        app(AssignmentService::class)->assign($class, [
            'class_session_id' => $session->id,
            'items' => [['type' => 'test', 'id' => $test->id]],
            'schedule' => 'now',
            'notify' => true,
        ], $this->teacher);

        Notification::assertSentTo($this->student, MissionAssigned::class);
    }
}
