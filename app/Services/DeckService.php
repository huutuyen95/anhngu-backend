<?php

namespace App\Services;

use App\Models\Deck;
use App\Models\SessionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeckService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $teacher): Deck
    {
        $classroomIds = $data['classroom_ids'] ?? [];

        return DB::transaction(function () use ($data, $teacher, $classroomIds) {
            $deck = Deck::create([
                'owner_id' => $teacher->id,
                'classroom_id' => $classroomIds[0] ?? null,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'tts_voice' => $data['tts_voice'] ?? 'en-GB-female',
                'tts_rate' => $data['tts_rate'] ?? 0.90,
                'tts_repeat' => $data['tts_repeat'] ?? '1',
                'is_public' => true,
                'is_published' => $data['is_published'] ?? false,
            ]);
            $deck->classrooms()->sync($classroomIds);

            return $deck;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Deck $deck, array $data): Deck
    {
        DB::transaction(function () use ($deck, $data) {
            $deck->fill(array_filter([
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'tts_voice' => $data['tts_voice'] ?? null,
                'tts_rate' => $data['tts_rate'] ?? null,
                'tts_repeat' => $data['tts_repeat'] ?? null,
                'is_published' => $data['is_published'] ?? null,
            ], fn ($k) => array_key_exists($k, $data), ARRAY_FILTER_USE_KEY));
            $deck->save();

            if (array_key_exists('classroom_ids', $data)) {
                $ids = $data['classroom_ids'] ?? [];
                $deck->classrooms()->sync($ids);
                $deck->update(['classroom_id' => $ids[0] ?? null]);
            }
        });

        return $deck->fresh();
    }

    /** Nhân bản bộ từ + toàn bộ thẻ. */
    public function duplicate(Deck $deck): Deck
    {
        return DB::transaction(function () use ($deck) {
            $copy = Deck::create([
                'owner_id' => $deck->owner_id,
                'classroom_id' => $deck->classroom_id,
                'name' => $deck->name.' (bản sao)',
                'slug' => $this->uniqueSlug($deck->name),
                'description' => $deck->description,
                'tts_voice' => $deck->tts_voice,
                'tts_rate' => $deck->tts_rate,
                'tts_repeat' => $deck->tts_repeat,
                'is_public' => true,
                'is_published' => false,
            ]);
            $copy->classrooms()->sync($deck->classrooms()->pluck('classrooms.id'));

            foreach ($deck->cards as $card) {
                $copy->cards()->create($card->only([
                    'order', 'term', 'meaning', 'pos', 'ipa', 'audio_url', 'image_url', 'example',
                ]));
            }

            return $copy;
        });
    }

    /**
     * Các buổi đang dùng bộ từ này (để chặn xoá). Rỗng = xoá được.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sessionsUsing(Deck $deck): array
    {
        return SessionItem::where('itemable_type', $deck->getMorphClass())
            ->where('itemable_id', $deck->id)
            ->with('classSession.classroom')
            ->get()
            ->map(fn (SessionItem $i) => [
                'id' => $i->classSession?->id,
                'title' => $i->classSession?->title,
                'classroom' => $i->classSession?->classroom?->name,
            ])
            ->values()
            ->all();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'deck';
        $slug = $base;
        $i = 1;
        while (Deck::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }
}
