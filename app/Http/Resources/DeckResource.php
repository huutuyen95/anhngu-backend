<?php

namespace App\Http\Resources;

use App\Models\Deck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deck
 */
class DeckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'tts_voice' => $this->tts_voice,
            'tts_rate' => (float) $this->tts_rate,
            'tts_repeat' => $this->tts_repeat,
            'is_published' => (bool) $this->is_published,
            'owner_name' => $this->whenLoaded('owner', fn () => $this->owner?->name),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'category_id' => $this->category_id,
            'classrooms' => $this->whenLoaded('classrooms', fn () => $this->classrooms->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])->values()),
            'classroom_ids' => $this->whenLoaded('classrooms', fn () => $this->classrooms->pluck('id')->values()),
            'cards_count' => $this->when($this->cards_count !== null, fn () => (int) $this->cards_count),
            'audio_ready_count' => $this->when($this->audio_ready_count !== null, fn () => (int) $this->audio_ready_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
