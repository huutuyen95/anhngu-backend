<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Repositories\StudentDeckRepository;

class StudentDeckService
{
    public function __construct(private readonly StudentDeckRepository $decks) {}

    public function library(User $u)
    {
        $classes = $this->decks->classIds($u->id);

        return $this->decks->library($u, $classes, $this->decks->missionDeckIds($u));
    }

    public function classroomId(User $u, ?int $id): ?int
    {
        if ($id !== null) {
            abort_unless($this->decks->isMember($id, $u->id), 403, 'Em không ở trong lớp này.');
        }

        return $id;
    }

    public function study(User $u, Deck $d, ?int $classId): array
    {
        $cards = $this->decks->cards($d);
        $statuses = $this->decks->progressStatuses($u->id, $cards->pluck('id'), $classId);
        $cards->each(fn (Card $c) => $c->progress_status = $statuses[$c->id] ?? 'new');

        return ['cards' => $cards, 'known' => $cards->filter(fn (Card $c) => $c->progress_status === 'known')->count()];
    }

    public function progress(User $u, Card $c, ?int $classId, string $status): string
    {
        return $this->decks->updateProgress($u->id, $c->id, $classId, $status)->status;
    }

    public function complete(User $u, Deck $d, ?int $classId, int $duration): array
    {
        $this->decks->logStudy($u, $d, $classId, $duration);
        $counts = $this->decks->completionCounts($u, $d, $classId);
        $done = false;
        $pct = (int) setting('content.deck_complete_pct', 80) / 100;
        if ($classId !== null && $counts['total'] > 0 && $pct <= $counts['known'] / $counts['total']) {
            $done = $this->decks->completeMissions($u, $d, $classId);
        }

        return $counts + ['mission_done' => $done];
    }
}
