<?php

namespace Tests\Feature;

use App\Models\Deck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeckTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_lists_published_shared_decks_via_library(): void
    {
        $user = User::factory()->create();
        // Bộ dùng chung, đã publish → học sinh thấy.
        Deck::create(['name' => 'Phương tiện', 'slug' => 'phuong-tien', 'is_public' => true, 'is_published' => true]);
        // Chưa publish → không thấy.
        Deck::create(['name' => 'Riêng tư', 'slug' => 'rieng-tu', 'is_public' => true, 'is_published' => false]);

        $this->actingAs($user)
            ->getJson('/api/v1/library/decks')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_guest_cannot_list_library_decks(): void
    {
        $this->getJson('/api/v1/library/decks')->assertStatus(401);
    }
}
