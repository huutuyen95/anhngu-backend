<?php

namespace Tests\Feature;

use App\Models\Deck;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSearchTest extends TestCase
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

    public function test_searches_published_tests_and_ignores_drafts(): void
    {
        Test::create(['created_by' => $this->teacher->id, 'title' => 'Reading Lake Baikal', 'slug' => 'r-lake',
            'skill' => 'reading', 'duration_minutes' => 30, 'total_score' => 10, 'is_published' => true]);
        Test::create(['created_by' => $this->teacher->id, 'title' => 'Reading Draft', 'slug' => 'r-draft',
            'skill' => 'reading', 'duration_minutes' => 30, 'total_score' => 10, 'is_published' => false]);

        $res = $this->actingAs($this->student)->getJson('/api/v1/me/search?q=Reading')->assertOk();
        $titles = collect($res->json('tests'))->pluck('title');
        $this->assertContains('Reading Lake Baikal', $titles);
        $this->assertNotContains('Reading Draft', $titles);
        $this->assertSame('/library/tests/'.Test::where('slug', 'r-lake')->value('id'), $res->json('tests.0.url'));
    }

    public function test_searches_shared_published_decks(): void
    {
        Deck::create(['owner_id' => $this->teacher->id, 'name' => 'GRADE 10 UNIT 5', 'slug' => 'g10u5',
            'is_public' => true, 'is_published' => true]);

        $res = $this->actingAs($this->student)->getJson('/api/v1/me/search?q=GRADE')->assertOk();
        $this->assertContains('GRADE 10 UNIT 5', collect($res->json('decks'))->pluck('title'));
    }

    public function test_searches_individual_words_inside_decks(): void
    {
        $deck = Deck::create(['owner_id' => $this->teacher->id, 'name' => 'GRADE 10 UNIT 5', 'slug' => 'g10',
            'is_public' => true, 'is_published' => true]);
        $deck->cards()->create(['order' => 1, 'term' => 'souvenir', 'meaning' => 'quà lưu niệm']);

        $res = $this->actingAs($this->student)->getJson('/api/v1/me/search?q=souvenir')->assertOk();
        $card = collect($res->json('cards'))->firstWhere('title', 'souvenir');
        $this->assertNotNull($card);
        $this->assertStringContainsString('GRADE 10 UNIT 5', $card['subtitle']);
        $this->assertStringContainsString("/library/vocab/{$deck->id}?term=", $card['url']);
    }

    public function test_query_is_required(): void
    {
        $this->actingAs($this->student)->getJson('/api/v1/me/search')->assertStatus(422);
    }
}
