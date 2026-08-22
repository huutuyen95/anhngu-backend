<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassSession;
use App\Notifications\SessionNote;
use App\Repositories\ClassSessionRepository;
use Illuminate\Support\Collection;

class ClassSessionService
{
    public function __construct(private readonly ClassSessionRepository $sessions) {}

    /**
     * Danh sách buổi của lớp kèm số liệu tiến trình.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listForClass(Classroom $classroom): Collection
    {
        $studentCount = $this->sessions->studentCount($classroom);

        return $this->sessions->sessions($classroom)->map(function (ClassSession $s) use ($studentCount) {
            $counts = $this->sessions->missionCounts($s->id);
            $total = $counts['total'];
            $done = $counts['done'];

            return [
                'id' => $s->id,
                'order' => $s->order,
                'title' => $s->title,
                'note' => $s->note,
                'held_on' => $s->held_on?->toDateString(),
                'is_visible' => (bool) $s->is_visible,
                'items_count' => $s->items_count,
                'done' => $done,
                'total' => $total,
                'progress_pct' => $total > 0 ? (int) round($done / $total * 100) : 0,
                'student_count' => $studentCount,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Classroom $classroom, array $data): ClassSession
    {
        return $this->sessions->create($classroom, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClassSession $session, array $data): ClassSession
    {
        $noteChanged = array_key_exists('note', $data) && filled($data['note']) && $data['note'] !== $session->note;

        $attributes = array_filter([
            'title' => $data['title'] ?? null,
            'note' => $data['note'] ?? null,
            'held_on' => $data['held_on'] ?? null,
        ], fn ($k) => array_key_exists($k, $data), ARRAY_FILTER_USE_KEY);

        $updated = $this->sessions->update($session, $attributes);

        // Cô vừa ghi chú buổi → báo cho học sinh trong lớp (theo notify.web + notify.on_session_open).
        if ($noteChanged && setting('notify.web', true) && setting('notify.on_session_open', false)) {
            $className = $this->sessions->classroomName($updated);
            foreach ($this->sessions->classroomStudents($updated) as $student) {
                $student->notify(new SessionNote($updated->classroom_id, $className, $updated->id, $updated->title));
            }
        }

        return $updated;
    }

    public function delete(ClassSession $session): void
    {
        $this->sessions->delete($session);
    }

    /**
     * @param  array<int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        $this->sessions->reorder($orderedIds);
    }

    /**
     * Đồng bộ toàn bộ tiến trình của lớp trong 1 request (tạo/sửa/xoá/đổi thứ tự).
     *
     * @param  array<int, array<string, mixed>>  $sessions
     * @param  array<int>  $deletedIds
     * @param  array<int>  $forceDeleteIds
     * @return array<string, mixed> ['blocked'=>[...]] nếu bị chặn xoá, ngược lại kết quả sync.
     */
    public function sync(Classroom $classroom, array $sessions, array $deletedIds, array $forceDeleteIds): array
    {
        // Buổi bị xoá mà còn nội dung/nhiệm vụ và KHÔNG được force → chặn lại.
        $blocked = [];
        foreach ($deletedIds as $id) {
            if (in_array($id, $forceDeleteIds, true)) {
                continue;
            }
            $session = $this->sessions->findInClass($classroom, $id);
            if (! $session) {
                continue;
            }
            $usage = $this->sessions->usageCounts($id);
            $items = $usage['items'];
            $missions = $usage['missions'];
            if ($items > 0 || $missions > 0) {
                $blocked[] = [
                    'id' => $id,
                    'title' => $session->title,
                    'items_count' => $items,
                    'missions_count' => $missions,
                ];
            }
        }

        if ($blocked) {
            return ['blocked' => $blocked];
        }

        $counts = $this->sessions->sync($classroom, $sessions, $deletedIds);

        return array_merge(
            ['ok' => true, 'sessions' => $this->listForClass($this->sessions->refreshClassroom($classroom))->values()],
            $counts,
        );
    }
}
