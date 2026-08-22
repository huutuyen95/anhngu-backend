<?php

namespace App\Repositories;

use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Mission;
use App\Models\SessionItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClassSessionRepository
{
    public function refreshClassroom(Classroom $classroom): Classroom
    {
        return $classroom->fresh();
    }

    public function studentCount(Classroom $c): int
    {
        return $c->students()->count();
    }

    /** Học sinh của lớp chứa buổi này (để gửi thông báo ghi chú). */
    public function classroomStudents(ClassSession $session): Collection
    {
        return $session->classroom
            ? $session->classroom->students()->get()
            : collect();
    }

    public function classroomName(ClassSession $session): string
    {
        return $session->classroom?->name ?? '';
    }

    public function sessions(Classroom $c): Collection
    {
        return $c->sessions()->withCount('items')->get();
    }

    public function missionCounts(int $id): array
    {
        $q = Mission::where('class_session_id', $id);

        return ['total' => (clone $q)->count(), 'done' => (clone $q)->where('status', 'done')->count()];
    }

    public function create(Classroom $c, array $data): ClassSession
    {
        $data['order'] = $data['order'] ?? (($c->sessions()->max('order') ?? 0) + 1);

        return $c->sessions()->create($data);
    }

    public function update(ClassSession $s, array $data): ClassSession
    {
        $s->update($data);

        return $s;
    }

    public function delete(ClassSession $s): void
    {
        $s->delete();
    }

    public function reorder(array $ids): void
    {
        DB::transaction(fn () => collect($ids)->each(fn ($id, $i) => ClassSession::whereKey($id)->update(['order' => $i + 1])));
    }

    public function findInClass(Classroom $c, int $id): ?ClassSession
    {
        return $c->sessions()->find($id);
    }

    public function usageCounts(int $id): array
    {
        return ['items' => SessionItem::where('class_session_id', $id)->count(), 'missions' => Mission::where('class_session_id', $id)->count()];
    }

    public function sync(Classroom $c, array $rows, array $deleted): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        DB::transaction(function () use ($c, $rows, $deleted, &$counts) {
            if ($deleted) {
                $counts['deleted'] = ClassSession::where('classroom_id', $c->id)->whereIn('id', $deleted)->delete();
            } foreach ($rows as $i => $row) {
                $data = ['title' => $row['title'], 'order' => $i + 1, 'is_visible' => $row['is_visible'] ?? true];
                if (! empty($row['id'])) {
                    ClassSession::where('classroom_id', $c->id)->whereKey($row['id'])->update($data);
                    $counts['updated']++;
                } else {
                    ClassSession::create(['classroom_id' => $c->id] + $data);
                    $counts['created']++;
                }
            }
        });

        return $counts;
    }
}
