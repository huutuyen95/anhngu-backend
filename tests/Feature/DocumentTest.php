<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentCategory;
use App\Models\Mission;
use App\Models\User;
use App\Services\DocumentService;
use Database\Seeders\IpaDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
    }

    private function makeDoc(array $attrs = []): Document
    {
        return Document::create(array_merge([
            'type' => 'document', 'title' => 'Tài liệu '.uniqid(), 'slug' => 'tl-'.uniqid(),
            'body' => '<p>Nội dung</p>', 'reading_minutes' => 1, 'is_published' => false,
            'created_by' => $this->teacher->id,
        ], $attrs));
    }

    public function test_sanitizes_script_from_body(): void
    {
        $this->actingAs($this->teacher)->postJson('/api/v1/documents', [
            'type' => 'document', 'title' => 'Có script',
            'body' => '<p>Xin chào</p><script>alert(1)</script><a href="javascript:alert(2)">x</a>',
        ])->assertCreated();

        $body = Document::where('title', 'Có script')->value('body');
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('javascript:', $body);
    }

    public function test_lecture_cannot_be_published(): void
    {
        $lecture = $this->makeDoc(['type' => 'lecture']);
        $this->actingAs($this->teacher)
            ->patchJson("/api/v1/documents/{$lecture->id}/publish", ['is_published' => true])
            ->assertStatus(422);
    }

    public function test_student_does_not_see_lecture_in_library_but_sees_if_assigned(): void
    {
        $class = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $student = User::factory()->create();
        $class->students()->attach($student->id, ['status' => 'studying']);

        $lecture = $this->makeDoc(['type' => 'lecture']);
        // Không hiện ở thư viện.
        $names = collect($this->actingAs($student)->getJson('/api/v1/library/documents')->json('data'))->pluck('title');
        $this->assertNotContains($lecture->title, $names);

        // Chưa giao → 403.
        $this->actingAs($student)->getJson("/api/v1/documents/{$lecture->id}/read")->assertStatus(403);

        // Giao vào buổi của lớp → đọc được.
        $session = $class->sessions()->create(['title' => 'B1', 'order' => 1]);
        $session->items()->create(['order' => 1, 'itemable_type' => $lecture->getMorphClass(), 'itemable_id' => $lecture->id]);
        $this->actingAs($student)->getJson("/api/v1/documents/{$lecture->id}/read")->assertOk();
    }

    public function test_unpublished_but_assigned_document_still_readable(): void
    {
        $class = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $student = User::factory()->create();
        $class->students()->attach($student->id, ['status' => 'studying']);
        $doc = $this->makeDoc(['is_published' => false]); // tắt thư viện
        $session = $class->sessions()->create(['title' => 'B1', 'order' => 1]);
        $session->items()->create(['order' => 1, 'itemable_type' => $doc->getMorphClass(), 'itemable_id' => $doc->id]);

        $this->actingAs($student)->getJson("/api/v1/documents/{$doc->id}/read")->assertOk();
    }

    public function test_view_80_percent_marks_completed_and_mission_done(): void
    {
        $class = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $student = User::factory()->create();
        $class->students()->attach($student->id, ['status' => 'studying']);
        $doc = $this->makeDoc(['is_published' => true]);
        $mission = Mission::create([
            'user_id' => $student->id, 'classroom_id' => $class->id,
            'missionable_type' => $doc->getMorphClass(), 'missionable_id' => $doc->id, 'status' => 'todo',
        ]);

        $this->actingAs($student)->postJson("/api/v1/documents/{$doc->id}/view", ['progress_pct' => 85])
            ->assertOk()->assertJsonPath('completed', true)->assertJsonPath('mission_done', true);

        $this->assertEquals('done', $mission->fresh()->status);
    }

    public function test_deleting_category_moves_documents_to_uncategorized(): void
    {
        $cat = DocumentCategory::create(['name' => 'Grammar', 'order' => 1]);
        $this->makeDoc(['category_id' => $cat->id]);
        $this->makeDoc(['category_id' => $cat->id]);

        $res = $this->actingAs($this->teacher)->putJson('/api/v1/document-categories/sync', [
            'categories' => [], 'deleted_ids' => [$cat->id],
        ])->assertOk();

        $this->assertEquals(2, $res->json('moved_count'));
        $fallback = DocumentCategory::where('name', 'Chưa phân loại')->first();
        $this->assertEquals(2, Document::where('category_id', $fallback->id)->count());
    }

    public function test_upload_exceeding_quota_is_blocked(): void
    {
        $doc = $this->makeDoc();
        // Lấp gần đầy quota bằng 1 bản ghi kích thước = quota.
        DocumentAttachment::create([
            'document_id' => $doc->id, 'name' => 'big.mp4', 'url' => 'x',
            'size_bytes' => DocumentService::QUOTA_BYTES, 'mime' => 'video/mp4', 'order' => 1, 'created_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->postJson("/api/v1/documents/{$doc->id}/attachments", ['file' => UploadedFile::fake()->create('small.pdf', 10)])
            ->assertStatus(422)->assertJsonPath('code', 'quota_exceeded');
    }

    public function test_dictionary_lemmatizes_conjugated_form(): void
    {
        $this->seed(IpaDictionarySeeder::class);
        $student = User::factory()->create();

        $this->actingAs($student)->getJson('/api/v1/dictionary?word=went')
            ->assertOk()->assertJsonPath('found', true)->assertJsonPath('word', 'go');

        $this->actingAs($student)->getJson('/api/v1/dictionary?word=zzxzz')
            ->assertOk()->assertJsonPath('found', false);
    }

    public function test_student_cannot_access_admin_documents(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/documents')->assertStatus(403);
    }
}
