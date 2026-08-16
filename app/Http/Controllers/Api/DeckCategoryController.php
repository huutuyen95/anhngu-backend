<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deck;
use App\Models\DeckCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeckCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->categories()]);
    }

    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['present', 'array'],
            'categories.*.id' => ['nullable', 'integer', 'exists:deck_categories,id'],
            'categories.*.name' => ['required', 'string', 'max:100'],
            'categories.*.order' => ['nullable', 'integer', 'min:0'],
            'deleted_ids' => ['array'],
            'deleted_ids.*' => ['integer', 'exists:deck_categories,id'],
        ]);

        DB::transaction(function () use ($data) {
            $deletedIds = $data['deleted_ids'] ?? [];
            if ($deletedIds !== []) {
                Deck::whereIn('category_id', $deletedIds)->update(['category_id' => null]);
                DeckCategory::whereIn('id', $deletedIds)->delete();
            }

            foreach ($data['categories'] as $index => $category) {
                $values = ['name' => trim($category['name']), 'order' => $category['order'] ?? $index + 1];
                if (! empty($category['id'])) {
                    DeckCategory::whereKey($category['id'])->update($values);
                } else {
                    DeckCategory::create($values);
                }
            }
        });

        return response()->json(['data' => $this->categories()]);
    }

    private function categories()
    {
        return DeckCategory::query()
            ->withCount('decks')
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn (DeckCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'order' => $category->order,
                'decks_count' => $category->decks_count,
            ]);
    }
}
