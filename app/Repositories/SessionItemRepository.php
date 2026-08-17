<?php

namespace App\Repositories;

use App\Models\Deck;
use App\Models\Mission;
use App\Models\SessionItem;
use Illuminate\Database\Eloquent\Collection;

class SessionItemRepository
{
    public function forSession(int $sessionId): Collection
    {
        return SessionItem::where('class_session_id', $sessionId)
            ->with('itemable')
            ->orderBy('order')
            ->get();
    }

    public function missionCounts(SessionItem $item): array
    {
        $missions = Mission::where('class_session_id', $item->class_session_id)
            ->where('missionable_type', $item->itemable_type)
            ->where('missionable_id', $item->itemable_id);

        return [
            'total' => (clone $missions)->count(),
            'done' => (clone $missions)->where('status', 'done')->count(),
        ];
    }

    public function deckCardCount(Deck $deck): int
    {
        return $deck->cards()->count();
    }

    public function delete(SessionItem $item): void
    {
        $item->delete();
    }
}
