<?php

namespace App\Repositories;

use App\Models\Card;
use App\Models\Deck;
use App\Models\Document;
use App\Models\Mission;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Collection;

/** Truy vấn tìm kiếm nội dung học sinh xem được (đề · từ vựng · tài liệu). */
class StudentSearchRepository
{
    /** Đề đã publish khớp từ khoá. */
    public function tests(string $q, int $limit): Collection
    {
        return Test::query()
            ->where('is_published', true)
            ->where('title', 'like', "%{$q}%")
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'skill']);
    }

    /** Bộ từ vựng student xem được (dùng chung / thuộc lớp / được giao). */
    public function decks(User $user, string $q, int $limit): Collection
    {
        $classIds = $user->classes()->pluck('classrooms.id');
        $missionDeckIds = Mission::query()
            ->where('user_id', $user->id)
            ->where('missionable_type', (new Deck)->getMorphClass())
            ->pluck('missionable_id');

        return Deck::query()
            ->where('is_published', true)
            ->where(fn ($x) => $x
                ->whereDoesntHave('classrooms')
                ->orWhereHas('classrooms', fn ($c) => $c->whereIn('classrooms.id', $classIds))
                ->orWhereIn('id', $missionDeckIds))
            ->where('name', 'like', "%{$q}%")
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name']);
    }

    /** Từ đơn (card) bên trong các bộ từ vựng học sinh xem được — khớp term hoặc nghĩa. */
    public function cards(User $user, string $q, int $limit): Collection
    {
        $classIds = $user->classes()->pluck('classrooms.id');
        $missionDeckIds = Mission::query()
            ->where('user_id', $user->id)
            ->where('missionable_type', (new Deck)->getMorphClass())
            ->pluck('missionable_id');

        return Card::query()
            ->where(fn ($x) => $x->where('term', 'like', "%{$q}%")->orWhere('meaning', 'like', "%{$q}%"))
            ->whereHas('deck', fn ($d) => $d->where('is_published', true)
                ->where(fn ($x) => $x
                    ->whereDoesntHave('classrooms')
                    ->orWhereHas('classrooms', fn ($c) => $c->whereIn('classrooms.id', $classIds))
                    ->orWhereIn('id', $missionDeckIds)))
            ->with('deck:id,name')
            ->orderBy('term')
            ->limit($limit)
            ->get(['id', 'term', 'meaning', 'deck_id']);
    }

    /** Tài liệu/bài giảng: thư viện (published + document) hoặc gán cho lớp của em. */
    public function documents(User $user, string $q, int $limit): Collection
    {
        $classIds = $user->classes()->pluck('classrooms.id');

        return Document::query()
            ->where('title', 'like', "%{$q}%")
            ->where(fn ($x) => $x
                ->where(fn ($d) => $d->where('is_published', true)->where('type', 'document'))
                ->orWhereHas('classrooms', fn ($c) => $c->whereIn('classrooms.id', $classIds)))
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'type']);
    }
}
