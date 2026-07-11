<?php

namespace Tests\Feature;

use App\Models\Deck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeckTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_public_decks(): void
    {
        $user = User::factory()->create();
        Deck::create(['name' => 'Phương tiện', 'slug' => 'phuong-tien', 'is_public' => true]);
        Deck::create(['name' => 'Riêng tư', 'slug' => 'rieng-tu', 'is_public' => false]);

        $response = $this->actingAs($user)->getJson('/api/v1/decks');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_guest_cannot_list_decks(): void
    {
        $response = $this->getJson('/api/v1/decks');

        $response->assertStatus(401);
    }
}
