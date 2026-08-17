<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use App\Models\CardProgress;
use App\Models\Classroom;
use App\Models\Deck;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentDeckRepository
{
    public function classIds(int $userId): Collection
    {
        return DB::table('class_user')->where('user_id', $userId)->pluck('classroom_id');
    }

    public function missionDeckIds(User $u): Collection
    {
        return Mission::where('user_id', $u->id)->where('missionable_type', (new Deck)->getMorphClass())->pluck('missionable_id');
    }

    public function library(User $u, Collection $classIds, Collection $missionIds): Collection
    {
        return Deck::query()->where('is_published', true)->where(fn ($q) => $q->whereDoesntHave('classrooms')->orWhereHas('classrooms', fn ($c) => $c->whereIn('classrooms.id', $classIds))->orWhereIn('id', $missionIds))->with(['category:id,name,order', 'classrooms' => fn ($q) => $q->whereIn('classrooms.id', $classIds)->select('classrooms.id', 'classrooms.name')])->withCount(['cards', 'cards as learned_count' => fn ($q) => $q->whereHas('progress', fn ($p) => $p->where('user_id', $u->id)->whereNull('classroom_id')->whereIn('status', ['learning', 'known']))])->orderBy('name')->get();
    }

    public function isMember(int $classId, int $userId): bool
    {
        return Classroom::whereKey($classId)->whereHas('students', fn ($q) => $q->whereKey($userId))->exists();
    }

    public function cards(Deck $d): Collection
    {
        return $d->cards()->get();
    }

    public function progressStatuses(int $userId, Collection $ids, ?int $classId): Collection
    {
        return CardProgress::where('user_id', $userId)->whereIn('card_id', $ids)->where(fn ($q) => $classId === null ? $q->whereNull('classroom_id') : $q->where('classroom_id', $classId))->pluck('status', 'card_id');
    }

    public function updateProgress(int $userId, int $cardId, ?int $classId, string $status): CardProgress
    {
        $p = CardProgress::updateOrCreate(['user_id' => $userId, 'card_id' => $cardId, 'classroom_id' => $classId], ['status' => $status, 'reviewed_at' => now()]);
        $p->increment('review_count');

        return $p;
    }

    public function logStudy(User $u, Deck $d, ?int $classId, int $duration): void
    {
        ActivityLog::create(['user_id' => $u->id, 'type' => 'deck_study', 'subject' => $d->name, 'duration_seconds' => $duration, 'meta' => ['deck_id' => $d->id, 'classroom_id' => $classId], 'created_at' => now()]);
    }

    public function completionCounts(User $u, Deck $d, ?int $classId): array
    {
        $ids = $d->cards()->pluck('id');
        $known = CardProgress::where('user_id', $u->id)->whereIn('card_id', $ids)->where(fn ($q) => $classId === null ? $q->whereNull('classroom_id') : $q->where('classroom_id', $classId))->where('status', 'known')->count();

        return ['known' => $known, 'total' => $ids->count()];
    }

    public function completeMissions(User $u, Deck $d, int $classId): bool
    {
        $missions = Mission::where('user_id', $u->id)->where('classroom_id', $classId)->where('missionable_type', $d->getMorphClass())->where('missionable_id', $d->id)->where('status', '!=', 'done')->get();
        foreach ($missions as $m) {
            $m->update(['status' => 'done', 'completed_at' => now()]);
        }

        return $missions->isNotEmpty();
    }
}
