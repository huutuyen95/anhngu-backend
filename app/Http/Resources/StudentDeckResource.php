<?php

namespace App\Http\Resources;

use App\Models\Deck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Deck */
class StudentDeckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cards_count' => (int) $this->cards_count,
            'learned_count' => (int) $this->learned_count,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'order' => $this->category->order,
            ] : null),
            'classrooms' => $this->whenLoaded('classrooms', fn () => $this->classrooms->map(fn ($classroom) => [
                'id' => $classroom->id,
                'name' => $classroom->name,
            ])->values()),
        ];
    }
}
