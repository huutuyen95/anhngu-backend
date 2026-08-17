<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\User;
use App\Repositories\ClassroomRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ClassroomService
{
    public function __construct(private readonly ClassroomStatsService $stats, private readonly ClassroomRepository $classrooms) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->classrooms->paginate($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{classroom: Classroom, warning: string|null}
     */
    public function create(array $data, User $teacher): array
    {
        $warning = $this->classrooms->nameExists($data['name'])
            ? 'Đã có lớp khác trùng tên — vẫn tạo được, cô kiểm tra lại nếu nhầm.'
            : null;

        $classroom = $this->classrooms->create([
            'teacher_id' => $teacher->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'cover_url' => $data['cover_url'] ?? null,
            'description' => $data['description'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'is_active' => true,
        ]);

        return ['classroom' => $classroom, 'warning' => $warning];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Classroom $classroom, array $data): Classroom
    {
        $attributes = array_filter([
            'name' => $data['name'] ?? null,
            'cover_url' => $data['cover_url'] ?? null,
            'description' => $data['description'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ], fn ($k) => array_key_exists($k, $data), ARRAY_FILTER_USE_KEY);
        $this->classrooms->update($classroom, $attributes);
        $this->stats->forget($classroom);

        return $classroom;
    }

    /** Xoá lớp: gỡ hết học viên khỏi lớp (KHÔNG xoá tài khoản) rồi xoá bản ghi lớp. */
    public function delete(Classroom $classroom): void
    {
        $this->stats->forget($classroom);
        $this->classrooms->delete($classroom);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'lop';
        $slug = $base;
        $i = 1;

        while ($this->classrooms->slugExists($slug)) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }

    public function studentCount(Classroom $classroom): int
    {
        return $this->classrooms->studentCount($classroom);
    }

    public function students(Classroom $classroom)
    {
        return $this->classrooms->students($classroom);
    }

    public function attachStudents(Classroom $classroom, array $ids): void
    {
        $this->classrooms->attachStudents($classroom, $ids);
    }

    public function detachStudent(Classroom $classroom, int $id): void
    {
        $this->classrooms->detachStudent($classroom, $id);
    }

    public function find(int $id): Classroom
    {
        return $this->classrooms->find($id);
    }
}
