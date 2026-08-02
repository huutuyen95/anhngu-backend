<?php

namespace App\Http\Controllers\Api;

use App\Enums\Skill;
use App\Http\Controllers\Controller;
use App\Models\Deck;
use App\Models\Question;
use App\Models\Document;
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
                ->take(50)
                ->get();

            // Test không có quan hệ trực tiếp tới Question (phải qua parts → sections),
            // nên đếm câu hỏi bằng join thay vì withCount('questions').
            $questionCounts = Question::query()
                ->join('test_sections', 'questions.test_section_id', '=', 'test_sections.id')
                ->join('test_parts', 'test_sections.test_part_id', '=', 'test_parts.id')
                ->whereIn('test_parts.test_id', $tests->pluck('id'))
                ->selectRaw('test_parts.test_id as test_id, count(*) as question_count')
                ->groupBy('test_parts.test_id')
                ->pluck('question_count', 'test_id');

            $items = $items->concat($tests->map(fn (Test $t) => [
                'type' => $type,
                'id' => $t->id,
                'title' => $t->title,
                'meta' => ($questionCounts[$t->id] ?? 0).' câu · '.($t->duration_minutes ?? 0).' phút',
            ]));
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

        if (in_array($type, ['document', 'lecture'], true)) {
            $docs = Document::query()
                ->where('type', $type)
                ->when($q, fn ($query, $term) => $query->where('title', 'like', "%{$term}%"))
                ->take(50)->get()
                ->map(fn (Document $d) => [
                    'type' => $type,
                    'id' => $d->id,
                    'title' => $d->title,
                    'meta' => ($type === 'lecture' ? 'Bài giảng' : 'Tài liệu').' · '.$d->reading_minutes.' phút đọc',
                ]);
            $items = $items->concat($docs);
        }

        return response()->json(['data' => $items->values()]);
    }
}
