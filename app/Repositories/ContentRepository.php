<?php

namespace App\Repositories;

use App\Enums\Skill;
use App\Models\Deck;
use App\Models\Document;
use App\Models\Question;
use App\Models\Test;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ContentRepository
{
    public function tests(string $type, ?string $search): Collection
    {
        return Test::query()
            ->when($type === 'writing', fn ($query) => $query->where('skill', Skill::Writing))
            ->when($type === 'test', fn ($query) => $query->where('skill', '!=', Skill::Writing))
            ->when($search, fn ($query, $term) => $query->where('title', 'like', "%{$term}%"))
            ->take(50)
            ->get();
    }

    public function questionCounts(SupportCollection $testIds): SupportCollection
    {
        return Question::query()
            ->join('test_sections', 'questions.test_section_id', '=', 'test_sections.id')
            ->join('test_parts', 'test_sections.test_part_id', '=', 'test_parts.id')
            ->whereIn('test_parts.test_id', $testIds)
            ->selectRaw('test_parts.test_id as test_id, count(*) as question_count')
            ->groupBy('test_parts.test_id')
            ->pluck('question_count', 'test_id');
    }

    public function decks(?string $search): Collection
    {
        return Deck::query()
            ->when($search, fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
            ->withCount('cards')
            ->take(50)
            ->get();
    }

    public function documents(string $type, ?string $search): Collection
    {
        return Document::query()
            ->where('type', $type)
            ->when($search, fn ($query, $term) => $query->where('title', 'like', "%{$term}%"))
            ->take(50)
            ->get();
    }
}
