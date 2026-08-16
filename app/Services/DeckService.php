<?php

namespace App\Services;

use App\Models\Card;
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
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'tts_voice' => $data['tts_voice'] ?? setting('tts.default_voice', 'en-GB-female'),
                'tts_rate' => $data['tts_rate'] ?? setting('tts.default_rate', 0.90),
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
                'category_id' => $data['category_id'] ?? null,
                'description' => $data['description'] ?? null,
                'tts_voice' => $data['tts_voice'] ?? null,
                'tts_rate' => $data['tts_rate'] ?? null,
                'tts_repeat' => $data['tts_repeat'] ?? null,
                'is_published' => $data['is_published'] ?? null,
            ], fn ($k) => array_key_exists($k, $data), ARRAY_FILTER_USE_KEY));
            $deck->save();

            if (array_key_exists('classroom_ids', $data)) {
                $deck->classrooms()->sync($data['classroom_ids'] ?? []);
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
                'category_id' => $deck->category_id,
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

            $now = now();
            $rows = $deck->cards->map(fn (Card $card) => [
                'deck_id' => $copy->id,
                'order' => $card->order,
                'term' => $card->term,
                'meaning' => $card->meaning,
                'pos' => $card->pos,
                'ipa' => $card->ipa,
                'audio_url' => $card->audio_url,
                'image_url' => $card->image_url,
                'example' => $card->example,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            if ($rows !== []) {
                Card::insert($rows);
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
