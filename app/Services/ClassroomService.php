<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Support\Str;

class ClassroomService
{
    public function __construct(private readonly ClassroomStatsService $stats) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{classroom: Classroom, warning: string|null}
     */
    public function create(array $data, User $teacher): array
    {
        $warning = Classroom::where('name', $data['name'])->exists()
            ? 'Đã có lớp khác trùng tên — vẫn tạo được, cô kiểm tra lại nếu nhầm.'
            : null;

        $classroom = Classroom::create([
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
        $classroom->fill(array_filter([
            'name' => $data['name'] ?? null,
            'cover_url' => $data['cover_url'] ?? null,
            'description' => $data['description'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ], fn ($k) => array_key_exists($k, $data), ARRAY_FILTER_USE_KEY));

        $classroom->save();
        $this->stats->forget($classroom);

        return $classroom;
    }

    /** Xoá lớp: gỡ hết học viên khỏi lớp (KHÔNG xoá tài khoản) rồi xoá bản ghi lớp. */
    public function delete(Classroom $classroom): void
    {
        $classroom->students()->detach();
        $this->stats->forget($classroom);
        $classroom->delete();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'lop';
        $slug = $base;
        $i = 1;

        while (Classroom::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }
}
