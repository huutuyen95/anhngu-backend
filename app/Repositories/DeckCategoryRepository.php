<?php

namespace App\Repositories;

use App\Models\Deck;
use App\Models\DeckCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeckCategoryRepository
{
    public function allWithCounts(): Collection
    {
        return DeckCategory::query()->withCount('decks')->orderBy('order')->orderBy('name')->get()
            ->map(fn (DeckCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'order' => $category->order,
                'decks_count' => $category->decks_count,
            ]);
    }

    public function sync(array $categories, array $deletedIds): void
    {
        DB::transaction(function () use ($categories, $deletedIds) {
            if ($deletedIds !== []) {
                Deck::whereIn('category_id', $deletedIds)->update(['category_id' => null]);
                DeckCategory::whereIn('id', $deletedIds)->delete();
            }
            foreach ($categories as $category) {
                $values = ['name' => $category['name'], 'order' => $category['order']];
                if ($category['id'] !== null) {
                    DeckCategory::whereKey($category['id'])->update($values);
                } else {
                    DeckCategory::create($values);
                }
            }
        });
    }
}
