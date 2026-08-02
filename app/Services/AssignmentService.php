<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\SessionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignmentService
{
    /** Map type từ FE → model class. */
    private const TYPE_MAP = [
        'test' => \App\Models\Test::class,
        'writing' => \App\Models\Test::class, // writing cũng là 1 Test (skill=writing)
        'deck' => \App\Models\Deck::class,
        'document' => \App\Models\Document::class,
        'lecture' => \App\Models\Document::class,
    ];

    /**
     * Giao bài: tạo session_items + missions cho từng học viên.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function assign(Classroom $classroom, array $data, User $teacher): array
    {
        $session = ClassSession::where('classroom_id', $classroom->id)
            ->findOrFail($data['class_session_id']);

        // Xác định học viên nhận bài.
        $targetIds = ($data['student_ids'] ?? null)
            ? collect($data['student_ids'])
            : $classroom->students()->pluck('users.id');

        $students = User::whereIn('id', $targetIds)
            ->where('role', UserRole::Student)
            ->get();

        $active = $students->where('is_active', true);
        $excludedLocked = $students->count() - $active->count();

        $status = match ($data['schedule'] ?? 'now') {
            'at' => 'scheduled',
            'draft' => 'draft',
            default => 'todo',
        };
        $scheduledAt = ($data['schedule'] ?? 'now') === 'at' ? ($data['scheduled_at'] ?? null) : null;

        $created = 0;
        $duplicates = 0;

        DB::transaction(function () use (
            $classroom, $session, $data, $teacher, $active, $status, $scheduledAt, &$created, &$duplicates
        ) {
            foreach ($data['items'] as $item) {
                $modelClass = self::TYPE_MAP[$item['type']] ?? null;
                if (! $modelClass) {
                    continue;
                }
                $model = $modelClass::find($item['id']);
                if (! $model) {
                    continue;
                }

                // Gắn nội dung vào buổi (nếu chưa có).
                SessionItem::firstOrCreate(
                    [
                        'class_session_id' => $session->id,
                        'itemable_type' => $model->getMorphClass(),
                        'itemable_id' => $model->id,
                    ],
                    ['order' => (SessionItem::where('class_session_id', $session->id)->max('order') ?? 0) + 1],
                );

                foreach ($active as $student) {
                    $exists = Mission::where('user_id', $student->id)
                        ->where('classroom_id', $classroom->id)
                        ->where('missionable_type', $model->getMorphClass())
                        ->where('missionable_id', $model->id)
                        ->exists();

                    if ($exists) {
                        $duplicates++;

                        continue;
                    }

                    Mission::create([
                        'user_id' => $student->id,
                        'assigned_by' => $teacher->id,
                        'classroom_id' => $classroom->id,
                        'class_session_id' => $session->id,
                        'missionable_type' => $model->getMorphClass(),
                        'missionable_id' => $model->id,
                        'source' => 'suggested',
                        'status' => $status,
                        'due_date' => $data['due_date'] ?? null,
                        'attempts_allowed' => $data['attempts_allowed'] ?? 1,
                        'scheduled_at' => $scheduledAt,
                    ]);
                    $created++;
                }
            }
        });

        app(ClassroomStatsService::class)->forget($classroom);

        $notify = ($data['notify'] ?? true) && $status === 'todo';

        return [
            'created' => $created,
            'students_targeted' => $active->count(),
            'excluded_locked' => $excludedLocked,
            'duplicates' => $duplicates,
            'notified' => $notify ? $active->count() : 0,
        ];
    }

    /** Nhắc học viên chưa làm bài trong 1 lượt giao. */
    public function remind(Classroom $classroom): int
    {
        // MVP: đếm số HS còn mission 'todo' trong lớp (thông báo thực gửi ở giai đoạn hạ tầng notify).
        return Mission::where('classroom_id', $classroom->id)
            ->where('status', 'todo')
            ->distinct('user_id')
            ->count('user_id');
    }
}
