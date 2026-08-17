<?php

namespace App\Services;

use App\Models\Deck;
use App\Models\User;
use App\Repositories\DeckRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class DeckService
{
    public function __construct(private readonly DeckRepository $decks) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->decks->paginate($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $teacher): Deck
    {
        $classroomIds = $data['classroom_ids'] ?? [];

        return $this->decks->create([
            'owner_id' => $teacher->id, 'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'], 'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'tts_voice' => $data['tts_voice'] ?? setting('tts.default_voice', 'en-GB-female'),
            'tts_rate' => $data['tts_rate'] ?? setting('tts.default_rate', 0.90),
            'tts_repeat' => $data['tts_repeat'] ?? '1', 'is_public' => true,
            'is_published' => $data['is_published'] ?? false,
        ], $classroomIds);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Deck $deck, array $data): Deck
    {
        $attributes = array_filter([
            'name' => $data['name'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'description' => $data['description'] ?? null,
            'tts_voice' => $data['tts_voice'] ?? null,
            'tts_rate' => $data['tts_rate'] ?? null,
            'tts_repeat' => $data['tts_repeat'] ?? null,
            'is_published' => $data['is_published'] ?? null,
        ], fn ($key) => array_key_exists($key, $data), ARRAY_FILTER_USE_KEY);

        return $this->decks->update($deck, $attributes, array_key_exists('classroom_ids', $data) ? ($data['classroom_ids'] ?? []) : null);
    }

    /** Nhân bản bộ từ + toàn bộ thẻ. */
    public function duplicate(Deck $deck): Deck
    {
        return $this->decks->duplicate($deck, [
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
    }

    /**
     * Các buổi đang dùng bộ từ này (để chặn xoá). Rỗng = xoá được.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sessionsUsing(Deck $deck): array
    {
        return $this->decks->sessionsUsing($deck);
    }

    public function detail(Deck $deck): Deck
    {
        return $this->decks->loadDetail($deck);
    }

    public function publish(Deck $deck, bool $published): Deck
    {
        return $this->decks->update($deck, ['is_published' => $published], null);
    }

    public function delete(Deck $deck): void
    {
        $this->decks->delete($deck);
    }

    public function cards(Deck $deck, array $filters): Collection
    {
        return $this->decks->cards($deck, $filters);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'deck';
        $slug = $base;
        $i = 1;
        while ($this->decks->slugExists($slug)) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }
}
