<?php

namespace App\Services;

use App\Models\Deck;
use App\Models\Document;
use App\Models\Test;
use App\Repositories\ContentRepository;
use Illuminate\Support\Collection;

class ContentService
{
    public function __construct(private readonly ContentRepository $repository) {}

    public function list(array $filters): Collection
    {
        $type = $filters['type'] ?? 'test';
        $search = $filters['q'] ?? null;
        $items = collect();

        if (in_array($type, ['test', 'writing'], true)) {
            $tests = $this->repository->tests($type, $search);
            $counts = $this->repository->questionCounts($tests->pluck('id'));
            $items = $items->concat($tests->map(fn (Test $test) => [
                'type' => $type,
                'id' => $test->id,
                'title' => $test->title,
                'meta' => ($counts[$test->id] ?? 0).' câu · '.($test->duration_minutes ?? 0).' phút',
            ]));
        }
        if ($type === 'deck') {
            $items = $items->concat($this->repository->decks($search)->map(fn (Deck $deck) => [
                'type' => 'deck',
                'id' => $deck->id,
                'title' => $deck->name,
                'meta' => ($deck->cards_count ?? 0).' từ',
            ]));
        }
        if (in_array($type, ['document', 'lecture'], true)) {
            $items = $items->concat($this->repository->documents($type, $search)->map(fn (Document $document) => [
                'type' => $type,
                'id' => $document->id,
                'title' => $document->title,
                'meta' => ($type === 'lecture' ? 'Bài giảng' : 'Tài liệu').' · '.$document->reading_minutes.' phút đọc',
            ]));
        }

        return $items->values();
    }
}
