<?php

namespace App\Http\Controllers\Api;

use App\Enums\Skill;
use App\Http\Controllers\Controller;
use App\Models\Deck;
use App\Models\Test;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    /**
     * Danh sách nội dung có thể giao (đề thi / writing / từ vựng), lọc theo loại + tìm kiếm.
     * (Tài liệu / Bài giảng sẽ bổ sung khi module nội dung hoàn thiện.)
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type', 'test');
        $q = $request->input('q');
        $items = collect();

        if (in_array($type, ['test', 'writing'], true)) {
            $tests = Test::query()
                ->when($type === 'writing', fn ($query) => $query->where('skill', Skill::Writing))
                ->when($type === 'test', fn ($query) => $query->where('skill', '!=', Skill::Writing))
                ->when($q, fn ($query, $term) => $query->where('title', 'like', "%{$term}%"))
                ->withCount('questions')
                ->take(50)
                ->get()
                ->map(fn (Test $t) => [
                    'type' => $type,
                    'id' => $t->id,
                    'title' => $t->title,
                    'meta' => ($t->questions_count ?? 0).' câu · '.($t->duration_minutes ?? 0).' phút',
                ]);
            $items = $items->concat($tests);
        }

        if ($type === 'deck') {
            $decks = Deck::query()
                ->when($q, fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
                ->withCount('cards')
                ->take(50)
                ->get()
                ->map(fn (Deck $d) => [
                    'type' => 'deck',
                    'id' => $d->id,
                    'title' => $d->name,
                    'meta' => ($d->cards_count ?? 0).' từ',
                ]);
            $items = $items->concat($decks);
        }

        return response()->json(['data' => $items->values()]);
    }
}
