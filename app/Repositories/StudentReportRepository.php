<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use App\Models\Mission;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Mọi truy vấn DB cho báo cáo học sinh (service chỉ tính toán, không chạm DB). */
class StudentReportRepository
{
    /** @return Collection<int,int> */
    public function classIds(User $student): Collection
    {
        return $student->classes()->pluck('classrooms.id');
    }

    public function isMember(User $student, int $classroomId): bool
    {
        return $student->classes()->whereKey($classroomId)->exists();
    }

    /** @return Collection<int,array{id:int,name:string}> */
    public function classes(User $student, Collection $classIds): Collection
    {
        return $student->classes()
            ->whereIn('classrooms.id', $classIds)
            ->get(['classrooms.id', 'classrooms.name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);
    }

    /** Lượt cô giao (kèm đề) từ mốc thời gian. */
    public function attempts(int $userId, Collection $classIds, Carbon $since): Collection
    {
        return TestAttempt::query()
            ->where('user_id', $userId)
            ->whereIn('classroom_id', $classIds)
            ->whereNotNull('mission_id')
            ->where('started_at', '>=', $since)
            ->with('test:id,title,skill,total_score')
            ->get();
    }

    public function missions(int $userId, Collection $classIds): Collection
    {
        return Mission::query()
            ->where('user_id', $userId)
            ->whereIn('classroom_id', $classIds)
            ->where('status', '!=', 'draft')
            ->get();
    }

    /** @return array{done:int,total:int} */
    public function classMissionCounts(int $userId, int $classroomId): array
    {
        $base = Mission::query()->where('user_id', $userId)->where('classroom_id', $classroomId)->where('status', '!=', 'draft');

        return ['done' => (clone $base)->where('status', 'done')->count(), 'total' => (clone $base)->count()];
    }

    public function deckLogs(int $userId, ?int $classroomId, Carbon $since): Collection
    {
        return ActivityLog::query()
            ->where('user_id', $userId)
            ->where('type', 'deck_study')
            ->where('created_at', '>=', $since)
            ->when($classroomId, fn ($q) => $q->where('meta->classroom_id', $classroomId))
            ->get();
    }

    public function activity7d(int $userId, ?int $classroomId): Collection
    {
        return ActivityLog::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->when($classroomId, fn ($q) => $q->where('meta->classroom_id', $classroomId))
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();
    }
}
