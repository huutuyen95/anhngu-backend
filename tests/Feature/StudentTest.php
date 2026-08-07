<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): User
    {
        return User::factory()->teacher()->create();
    }

    public function test_teacher_can_list_and_filter_students(): void
    {
        $teacher = $this->teacher();
        User::factory()->create(['name' => 'An Nguyen', 'email' => 'an@example.com']);
        User::factory()->create(['name' => 'Binh Tran', 'email' => 'binh@example.com']);

        $this->actingAs($teacher)
            ->getJson('/api/v1/students?q=nguyen')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'an@example.com');
    }

    public function test_creating_student_returns_temp_password_once(): void
    {
        $this->actingAs($this->teacher())
            ->postJson('/api/v1/students', [
                'name' => 'New Student',
                'email' => 'new@example.com',
            ])
            ->assertCreated()
            ->assertJsonStructure(['student' => ['id', 'email'], 'temp_password']);

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'student']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->actingAs($this->teacher())
            ->postJson('/api/v1/students', ['name' => 'X', 'email' => 'dup@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_bulk_lock_disables_accounts(): void
    {
        $teacher = $this->teacher();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($teacher)
            ->postJson('/api/v1/students/bulk', ['action' => 'lock', 'ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJson(['affected' => 2]);

        $this->assertFalse($a->fresh()->is_active);
        $this->assertFalse($b->fresh()->is_active);
    }

    public function test_soft_delete_then_restore(): void
    {
        $teacher = $this->teacher();
        $student = User::factory()->create();

        $this->actingAs($teacher)->deleteJson("/api/v1/students/{$student->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $student->id]);

        $this->actingAs($teacher)->postJson("/api/v1/students/{$student->id}/restore")->assertOk();
        $this->assertNotSoftDeleted('users', ['id' => $student->id]);
    }

    public function test_assign_class_add_and_move_modes(): void
    {
        $teacher = $this->teacher();
        $student = User::factory()->create();
        $classA = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $classB = Classroom::create(['teacher_id' => $teacher->id, 'name' => 'B', 'slug' => 'b', 'is_active' => true]);

        // add: giữ lớp cũ
        $this->actingAs($teacher)->postJson('/api/v1/students/bulk', [
            'action' => 'assign_class', 'ids' => [$student->id], 'classroom_id' => $classA->id, 'mode' => 'add',
        ])->assertOk();
        $this->actingAs($teacher)->postJson('/api/v1/students/bulk', [
            'action' => 'assign_class', 'ids' => [$student->id], 'classroom_id' => $classB->id, 'mode' => 'add',
        ])->assertOk();
        $this->assertEqualsCanonicalizing([$classA->id, $classB->id], $student->classes()->pluck('classrooms.id')->all());

        // move: chỉ còn lớp mới
        $this->actingAs($teacher)->postJson('/api/v1/students/bulk', [
            'action' => 'assign_class', 'ids' => [$student->id], 'classroom_id' => $classA->id, 'mode' => 'move',
        ])->assertOk();
        $this->assertEquals([$classA->id], $student->classes()->pluck('classrooms.id')->all());
    }

    public function test_import_dry_run_does_not_write_db(): void
    {
        Excel::fake();
        $teacher = $this->teacher();

        // Giả lập parser trả 2 dòng: 1 hợp lệ, 1 thiếu email.
        Excel::shouldReceive('toArray')->andReturn([[
            ['name' => 'Valid One', 'email' => 'valid1@example.com', 'phone' => null, 'class' => null, 'note' => null],
            ['name' => 'No Email', 'email' => '', 'phone' => null, 'class' => null, 'note' => null],
        ]]);

        $this->actingAs($teacher)
            ->postJson('/api/v1/students/import?dry_run=1', [
                'file' => UploadedFile::fake()->create('students.xlsx', 10),
            ])
            ->assertOk()
            ->assertJsonPath('summary.ok', 1)
            ->assertJsonPath('summary.error', 1);

        $this->assertDatabaseMissing('users', ['email' => 'valid1@example.com']);
    }

    public function test_check_email_reports_availability(): void
    {
        $teacher = $this->teacher();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($teacher)->getJson('/api/v1/students/check-email?email=free@example.com')
            ->assertOk()->assertJson(['available' => true]);
        $this->actingAs($teacher)->getJson('/api/v1/students/check-email?email=taken@example.com')
            ->assertOk()->assertJson(['available' => false]);
    }

    public function test_import_update_mode_updates_existing_student(): void
    {
        Excel::fake();
        $teacher = $this->teacher();
        $existing = User::factory()->create(['email' => 'dup@example.com', 'name' => 'Old Name']);

        Excel::shouldReceive('toArray')->andReturn([[
            ['name' => 'New Name', 'email' => 'dup@example.com', 'phone' => '0900', 'class' => null, 'note' => null],
        ]]);

        $this->actingAs($teacher)
            ->postJson('/api/v1/students/import?dry_run=0', [
                'file' => UploadedFile::fake()->create('s.xlsx', 10),
                'on_duplicate' => 'update',
            ])
            ->assertOk()
            ->assertJsonPath('summary.updated', 1);

        $this->assertEquals('New Name', $existing->fresh()->name);
    }

    public function test_student_cannot_access_students_api(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->getJson('/api/v1/students')
            ->assertStatus(403);
    }
}
