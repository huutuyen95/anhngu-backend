<?php

namespace App\Repositories;

use App\Models\Card;
use App\Models\Deck;
use App\Models\SessionItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DeckRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Deck::query()
            ->with(['owner:id,name', 'category:id,name', 'classrooms:id,name'])
            ->withCount(['cards', 'cards as audio_ready_count' => fn ($query) => $query->where(fn ($where) => $where->whereNotNull('audio_url')->orWhereNotNull('ipa'))])
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where('name', 'like', "%{$value}%"))
            ->when($filters['classroom_id'] ?? null, fn ($query, $id) => $query->whereHas('classrooms', fn ($classrooms) => $classrooms->where('classrooms.id', $id)))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when(array_key_exists('is_published', $filters), fn ($query) => $query->where('is_published', $filters['is_published']))
            ->latest()->paginate($filters['per_page'] ?? 24);
    }

    public function create(array $data, array $classroomIds): Deck
    {
        return DB::transaction(function () use ($data, $classroomIds) {
            $deck = Deck::create($data);
            $deck->classrooms()->sync($classroomIds);

            return $deck;
        });
    }

    public function update(Deck $deck, array $data, ?array $classroomIds): Deck
    {
        DB::transaction(function () use ($deck, $data, $classroomIds) {
            $deck->update($data);
            if ($classroomIds !== null) {
                $deck->classrooms()->sync($classroomIds);
            }
        });

        return $deck->fresh();
    }

    public function loadDetail(Deck $deck): Deck
    {
        return $deck->load(['owner:id,name', 'category:id,name', 'classrooms:id,name'])
            ->loadCount(['cards', 'cards as audio_ready_count' => fn ($query) => $query->where(fn ($where) => $where->whereNotNull('audio_url')->orWhereNotNull('ipa'))]);
    }

    public function duplicate(Deck $deck, array $attributes): Deck
    {
        return DB::transaction(function () use ($deck, $attributes) {
            $copy = Deck::create($attributes);
            $copy->classrooms()->sync($deck->classrooms()->pluck('classrooms.id'));
            $now = now();
            $rows = $deck->cards->map(fn (Card $card) => [
                'deck_id' => $copy->id, 'order' => $card->order, 'term' => $card->term,
                'meaning' => $card->meaning, 'pos' => $card->pos, 'ipa' => $card->ipa,
                'audio_url' => $card->audio_url, 'image_url' => $card->image_url,
                'example' => $card->example, 'created_at' => $now, 'updated_at' => $now,
            ])->all();
            if ($rows !== []) {
                Card::insert($rows);
            }

            return $copy;
        });
    }

    public function sessionsUsing(Deck $deck): array
    {
        return SessionItem::where('itemable_type', $deck->getMorphClass())->where('itemable_id', $deck->id)
            ->with('classSession.classroom')->get()->map(fn (SessionItem $item) => [
                'id' => $item->classSession?->id,
                'title' => $item->classSession?->title,
                'classroom' => $item->classSession?->classroom?->name,
            ])->values()->all();
    }

    public function slugExists(string $slug): bool
    {
        return Deck::where('slug', $slug)->exists();
    }

    public function delete(Deck $deck): void
    {
        $deck->delete();
    }

    public function cards(Deck $deck, array $filters): Collection
    {
        return $deck->cards()->when($filters['q'] ?? null, fn ($query, $value) => $query->where(fn ($where) => $where->where('term', 'like', "%{$value}%")->orWhere('meaning', 'like', "%{$value}%")->orWhere('example', 'like', "%{$value}%")))
            ->when(($filters['missing'] ?? null) === 'audio', fn ($query) => $query->whereNull('audio_url')->whereNull('ipa'))
            ->when(($filters['missing'] ?? null) === 'image', fn ($query) => $query->whereNull('image_url'))
            ->when(($filters['missing'] ?? null) === 'ipa', fn ($query) => $query->whereNull('ipa'))
            ->when(($filters['missing'] ?? null) === 'example', fn ($query) => $query->where(fn ($where) => $where->whereNull('example')->orWhere('example', '')))
            ->orderBy('order')->get();
    }
}
