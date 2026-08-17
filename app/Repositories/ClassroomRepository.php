<?php

namespace App\Repositories;

use App\Models\Classroom;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClassroomRepository
{
    public function paginate(array $f): LengthAwarePaginator
    {
        $today = now()->toDateString();

        return Classroom::query()->when($f['q'] ?? null, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))->when($f['status'] ?? null, function ($q, $s) use ($today) {
            match ($s) {
                'upcoming' => $q->whereNotNull('starts_on')->whereDate('starts_on', '>', $today),'ended' => $q->whereNotNull('ends_on')->whereDate('ends_on', '<', $today),'active' => $q->where(fn ($x) => $x->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))->where(fn ($x) => $x->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today)),default => $q
            };
        })->latest()->paginate($f['per_page'] ?? 24);
    }

    public function nameExists(string $name): bool
    {
        return Classroom::where('name', $name)->exists();
    }

    public function slugExists(string $slug): bool
    {
        return Classroom::where('slug', $slug)->exists();
    }

    public function create(array $data): Classroom
    {
        return Classroom::create($data);
    }

    public function update(Classroom $c, array $data): Classroom
    {
        $c->update($data);

        return $c;
    }

    public function studentCount(Classroom $c): int
    {
        return $c->students()->count();
    }

    public function delete(Classroom $c): void
    {
        $c->students()->detach();
        $c->delete();
    }

    public function students(Classroom $c): Collection
    {
        return $c->students()->withCount(['testAttempts as in_progress_attempts_count' => fn ($q) => $q->where('status', 'in_progress')])->orderBy('name')->get();
    }

    public function attachStudents(Classroom $c, array $ids): void
    {
        $c->students()->syncWithoutDetaching(collect($ids)->mapWithKeys(fn ($id) => [$id => ['status' => 'studying']])->all());
    }

    public function detachStudent(Classroom $c, int $id): void
    {
        $c->students()->detach($id);
    }

    public function find(int $id): Classroom
    {
        return Classroom::findOrFail($id);
    }
}
