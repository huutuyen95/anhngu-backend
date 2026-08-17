<?php

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StudentRepository
{
    public function paginate(array $f): LengthAwarePaginator
    {
        return User::query()->where('role', UserRole::Student)->when(! empty($f['trashed']), fn ($q) => $q->onlyTrashed())->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($x) => $x->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")->orWhere('phone', 'like', "%{$v}%")))->when(array_key_exists('is_active', $f), fn ($q) => $q->where('is_active', $f['is_active']))->when($f['classroom_id'] ?? null, fn ($q, $id) => $q->whereHas('classes', fn ($c) => $c->where('classrooms.id', $id)))->with('classes:id,name')->withCount(['testAttempts as in_progress_attempts_count' => fn ($q) => $q->where('status', 'in_progress')])->orderBy($f['sort'] ?? 'created_at', $f['dir'] ?? 'desc')->paginate($f['per_page'] ?? 15)->withQueryString();
    }

    public function create(array $data, array $classIds): User
    {
        return DB::transaction(function () use ($data, $classIds) {
            $u = User::create($data);
            $this->syncClasses($u, $classIds);

            return $u->load('classes:id,name');
        });
    }

    public function update(User $u, array $data, ?array $classIds): User
    {
        return DB::transaction(function () use ($u, $data, $classIds) {
            $u->update($data);
            if ($classIds !== null) {
                $this->syncClasses($u, $classIds);
            }

            return $u->load('classes:id,name');
        });
    }

    public function resolve(int $id, bool $trashed = false): User
    {
        $q = User::query()->where('role', UserRole::Student);
        if ($trashed) {
            $q->withTrashed();
        }

        return $q->findOrFail($id);
    }

    public function delete(User $u, bool $force): void
    {
        $force ? $u->forceDelete() : $u->delete();
    }

    public function restore(User $u): User
    {
        $u->restore();

        return $u->load('classes:id,name');
    }

    public function updateFields(User $u, array $data): User
    {
        $u->update($data);

        return $u;
    }

    public function bulkUpdate(array $ids, array $data): int
    {
        return User::whereIn('id', $ids)->update($data);
    }

    public function bulkDelete(array $ids): int
    {
        return User::whereIn('id', $ids)->delete();
    }

    public function assignClass(array $ids, int $classId, string $mode): int
    {
        $c = Classroom::findOrFail($classId);
        DB::transaction(function () use ($ids, $c, $mode) {
            foreach ($ids as $id) {
                $u = User::find($id);
                if (! $u) {
                    continue;
                }if ($mode === 'move') {
                    $u->classes()->detach();
                }$u->classes()->syncWithoutDetaching([$c->id => ['status' => 'studying']]);
            }
        });

        return count($ids);
    }

    public function emailExists(string $email, ?int $ignore = null): bool
    {
        return User::withTrashed()->where('email', $email)->when($ignore, fn ($q, $id) => $q->whereKeyNot($id))->exists();
    }

    public function classroomByName(string $name): ?Classroom
    {
        return Classroom::where('name', $name)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function transaction(callable $cb): mixed
    {
        return DB::transaction($cb);
    }

    private function syncClasses(User $u, array $ids): void
    {
        $u->classes()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['status' => 'studying']])->all());
    }
}
