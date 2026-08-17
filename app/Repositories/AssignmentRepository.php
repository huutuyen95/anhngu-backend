<?php

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Mission;
use App\Models\SessionItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssignmentRepository
{
    public function classroom(int $id): Classroom
    {
        return Classroom::findOrFail($id);
    }

    public function session(Classroom $c, int $id): ClassSession
    {
        return ClassSession::where('classroom_id', $c->id)->findOrFail($id);
    }

    public function studentIds(Classroom $c): Collection
    {
        return $c->students()->pluck('users.id');
    }

    public function students(iterable $ids): Collection
    {
        return User::whereIn('id', $ids)->where('role', UserRole::Student)->get();
    }

    public function content(string $class, int $id): mixed
    {
        return $class::find($id);
    }

    public function assignItem(ClassSession $s, mixed $model): void
    {
        SessionItem::firstOrCreate(['class_session_id' => $s->id, 'itemable_type' => $model->getMorphClass(), 'itemable_id' => $model->id], ['order' => (SessionItem::where('class_session_id', $s->id)->max('order') ?? 0) + 1]);
    }

    public function missionExists(int $userId, Classroom $c, mixed $model): bool
    {
        return Mission::where('user_id', $userId)->where('classroom_id', $c->id)->where('missionable_type', $model->getMorphClass())->where('missionable_id', $model->id)->exists();
    }

    public function createMission(array $data): void
    {
        Mission::create($data);
    }

    public function transaction(callable $cb): mixed
    {
        return DB::transaction($cb);
    }

    public function todoCount(Classroom $c): int
    {
        return Mission::where('classroom_id', $c->id)->where('status', 'todo')->distinct('user_id')->count('user_id');
    }
}
