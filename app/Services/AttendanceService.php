<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\SessionAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * Danh sách điểm danh của 1 buổi: mỗi học viên trong lớp + trạng thái/nhận xét (nếu có).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forSession(ClassSession $session): array
    {
        $students = $session->classroom->students()->orderBy('name')->get();
        $records = SessionAttendance::where('class_session_id', $session->id)
            ->get()
            ->keyBy('user_id');

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
        return DB::transaction(function () use ($session, $items, $teacher) {
            $count = 0;
            foreach ($items as $item) {
                if (empty($item['user_id'])) {
                    continue;
                }
                SessionAttendance::updateOrCreate(
                    ['class_session_id' => $session->id, 'user_id' => $item['user_id']],
                    [
                        'status' => $item['status'] ?? 'on_time',
                        'comment' => $item['comment'] ?? null,
                        'updated_by' => $teacher->id,
                    ],
                );
                $count++;
            }

            return $count;
        });
    }
}
