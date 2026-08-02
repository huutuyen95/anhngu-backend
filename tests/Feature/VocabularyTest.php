<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Classroom;
use App\Models\Deck;
use App\Models\IpaEntry;
use App\Models\Mission;
use App\Models\User;
use Database\Seeders\IpaDictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class VocabularyTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
    }

    private function makeDeck(array $attrs = []): Deck
    {
        return Deck::create(array_merge([
            'owner_id' => $this->teacher->id,
            'name' => 'Bộ '.uniqid(),
            'slug' => 'bo-'.uniqid(),
            'is_public' => true,
            'is_published' => false,
        ], $attrs));
    }

    public function test_teacher_lists_decks_filtered_by_classroom(): void
    {
        $class = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $d1 = $this->makeDeck(['name' => 'Có lớp']);
        $d1->classrooms()->sync([$class->id]);
        $this->makeDeck(['name' => 'Không lớp']);

        $this->actingAs($this->teacher)
            ->getJson("/api/v1/decks?classroom_id={$class->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Có lớp');
    }

    public function test_create_deck_assigns_two_classrooms(): void
    {
        $c1 = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $c2 = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'B', 'slug' => 'b', 'is_active' => true]);

        $id = $this->actingAs($this->teacher)
            ->postJson('/api/v1/decks', ['name' => 'GRADE 6 UNIT 1', 'classroom_ids' => [$c1->id, $c2->id]])
            ->assertCreated()
            ->json('deck.id');

        $this->assertEquals(2, Deck::find($id)->classrooms()->count());
    }

    public function test_deleting_deck_in_use_is_blocked(): void
    {
        $deck = $this->makeDeck();
        $class = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $session = $class->sessions()->create(['title' => 'B1', 'order' => 1]);
        $session->items()->create(['order' => 1, 'itemable_type' => $deck->getMorphClass(), 'itemable_id' => $deck->id]);

        $this->actingAs($this->teacher)
            ->deleteJson("/api/v1/decks/{$deck->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'deck_in_use')
            ->assertJsonPath('sessions.0.title', 'B1');

        $this->assertDatabaseHas('decks', ['id' => $deck->id]);
    }

    public function test_reorder_cards(): void
    {
        $deck = $this->makeDeck();
        $a = $deck->cards()->create(['order' => 1, 'term' => 'a', 'meaning' => 'x']);
        $b = $deck->cards()->create(['order' => 2, 'term' => 'b', 'meaning' => 'y']);

        $this->actingAs($this->teacher)
            ->putJson("/api/v1/decks/{$deck->id}/cards/reorder", ['ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertEquals(1, Card::find($b->id)->order);
        $this->assertEquals(2, Card::find($a->id)->order);
    }

    public function test_import_dry_run_does_not_write_and_commit_autofills_ipa(): void
    {
        $this->seed(IpaDictionarySeeder::class);
        $deck = $this->makeDeck();
        Excel::fake();
        Excel::shouldReceive('toArray')->andReturn([[
            ['term' => 'journey', 'meaning' => 'chuyến đi', 'ipa' => '', 'pos' => 'n.', 'example' => ''],
            ['term' => '', 'meaning' => 'lỗi', 'ipa' => '', 'pos' => '', 'example' => ''],
        ]]);

        // dry_run: không ghi DB, journey được đánh dấu need_ipa (có trong từ điển).
        $this->actingAs($this->teacher)
            ->postJson("/api/v1/decks/{$deck->id}/cards/import?dry_run=1", ['file' => UploadedFile::fake()->create('c.xlsx', 5)])
            ->assertOk()
            ->assertJsonPath('summary.need_ipa', 1)
            ->assertJsonPath('summary.error', 1);
        $this->assertEquals(0, $deck->cards()->count());

        // commit: tạo thẻ journey, tự điền IPA.
        $this->actingAs($this->teacher)
            ->postJson("/api/v1/decks/{$deck->id}/cards/import", ['file' => UploadedFile::fake()->create('c.xlsx', 5)])
            ->assertOk()
            ->assertJsonPath('created', 1);

        $card = $deck->cards()->where('term', 'journey')->first();
        $this->assertNotNull($card->ipa);
    }

    public function test_ipa_lookup(): void
    {
        IpaEntry::create(['word' => 'souvenir', 'ipa' => '/x/', 'pos' => 'n.']);

        $this->actingAs($this->teacher)
            ->getJson('/api/v1/ipa/lookup?words=souvenir,unknownword')
            ->assertOk()
            ->assertJsonPath('results.souvenir.ipa', '/x/');
    }

    public function test_student_only_sees_published_decks_for_their_class(): void
    {
        $class = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $student = User::factory()->create();
        $class->students()->attach($student->id, ['status' => 'studying']);

        $shared = $this->makeDeck(['name' => 'Chung', 'is_published' => true]); // dùng chung
        $mine = $this->makeDeck(['name' => 'Lớp mình', 'is_published' => true]);
        $mine->classrooms()->sync([$class->id]);
        $other = $this->makeDeck(['name' => 'Lớp khác', 'is_published' => true]);
        $otherClass = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'X', 'slug' => 'x', 'is_active' => true]);
        $other->classrooms()->sync([$otherClass->id]);
        $this->makeDeck(['name' => 'Chưa publish', 'is_published' => false]);

        $names = collect($this->actingAs($student)->getJson('/api/v1/library/decks')->assertOk()->json('data'))->pluck('name');

        $this->assertContains('Chung', $names);
        $this->assertContains('Lớp mình', $names);
        $this->assertNotContains('Lớp khác', $names);
        $this->assertNotContains('Chưa publish', $names);
    }

    public function test_studying_80_percent_marks_mission_done(): void
    {
        $class = Classroom::create(['teacher_id' => $this->teacher->id, 'name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $student = User::factory()->create();
        $class->students()->attach($student->id, ['status' => 'studying']);
        $deck = $this->makeDeck(['is_published' => true]);
        $cards = collect(range(1, 5))->map(fn ($i) => $deck->cards()->create(['order' => $i, 'term' => "w{$i}", 'meaning' => "m{$i}"]));

        $mission = Mission::create([
            'user_id' => $student->id, 'classroom_id' => $class->id,
            'missionable_type' => $deck->getMorphClass(), 'missionable_id' => $deck->id, 'status' => 'todo',
        ]);

        // 4/5 known = 80%
        foreach ($cards->take(4) as $card) {
            $this->actingAs($student)->putJson("/api/v1/cards/{$card->id}/progress", ['status' => 'known'])->assertOk();
        }

        $this->actingAs($student)->postJson("/api/v1/decks/{$deck->id}/session-complete", ['duration_seconds' => 60])
            ->assertOk()->assertJsonPath('mission_done', true);

        $this->assertEquals('done', $mission->fresh()->status);
    }

    public function test_student_cannot_access_admin_deck_api(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/decks')
            ->assertStatus(403);
    }
}
