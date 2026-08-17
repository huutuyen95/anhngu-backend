<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\User;
use App\Repositories\AttendanceRepository;

class AttendanceService
{
    public function __construct(private readonly AttendanceRepository $attendance) {}

    /**
     * Danh sách điểm danh của 1 buổi: mỗi học viên trong lớp + trạng thái/nhận xét (nếu có).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forSession(ClassSession $session): array
    {
        $students = $this->attendance->students($session);
        $records = $this->attendance->records($session);

        return $students->map(function (User $s) use ($records) {
            $rec = $records->get($s->id);

            return [
                'user_id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
                'status' => $rec?->status,
                'comment' => $rec?->comment ?? '',
            ];
        })->all();
    }

    /**
     * Upsert điểm danh + nhận xét (gọi nhiều lần KHÔNG tạo bản ghi trùng nhờ UNIQUE).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function bulkUpsert(ClassSession $session, array $items, User $teacher): int
    {
        return $this->attendance->upsert($session, $items, $teacher);
    }
}
