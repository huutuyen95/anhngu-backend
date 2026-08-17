<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Deck;
use App\Models\Document;
use App\Models\Test;
use App\Models\User;
use App\Repositories\AssignmentRepository;

class AssignmentService
{
    public function __construct(private readonly AssignmentRepository $assignments) {}

    /** Map type từ FE → model class. */
    private const TYPE_MAP = [
        'test' => Test::class,
        'writing' => Test::class, // writing cũng là 1 Test (skill=writing)
        'deck' => Deck::class,
        'document' => Document::class,
        'lecture' => Document::class,
    ];

    /**
     * Giao bài: tạo session_items + missions cho từng học viên.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function assign(Classroom $classroom, array $data, User $teacher): array
    {
        $session = $this->assignments->session($classroom, $data['class_session_id']);

        // Xác định học viên nhận bài.
        $targetIds = ($data['student_ids'] ?? null)
            ? collect($data['student_ids'])
            : $this->assignments->studentIds($classroom);

        $students = $this->assignments->students($targetIds);

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

        $this->assignments->transaction(function () use (
            $classroom, $session, $data, $teacher, $active, $status, $scheduledAt, &$created, &$duplicates
        ) {
            foreach ($data['items'] as $item) {
                $modelClass = self::TYPE_MAP[$item['type']] ?? null;
                if (! $modelClass) {
                    continue;
                }
                $model = $this->assignments->content($modelClass, $item['id']);
                if (! $model) {
                    continue;
                }

                // Gắn nội dung vào buổi (nếu chưa có).
                $this->assignments->assignItem($session, $model);

                foreach ($active as $student) {
                    $exists = $this->assignments->missionExists($student->id, $classroom, $model);

                    if ($exists) {
                        $duplicates++;

                        continue;
                    }

                    $this->assignments->createMission([
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
        return $this->assignments->todoCount($classroom);
    }

    public function classroom(int $id): Classroom
    {
        return $this->assignments->classroom($id);
    }
}
