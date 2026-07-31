<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\Mission;
use App\Models\SessionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ClassSessionService
{
    /**
     * Danh sách buổi của lớp kèm số liệu tiến trình.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listForClass(Classroom $classroom): Collection
    {
        $studentCount = $classroom->students()->count();

        return $classroom->sessions()->withCount('items')->get()->map(function (ClassSession $s) use ($studentCount) {
            $missions = Mission::where('class_session_id', $s->id);
            $total = (clone $missions)->count();
            $done = (clone $missions)->where('status', 'done')->count();

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
        $order = $data['order'] ?? (($classroom->sessions()->max('order') ?? 0) + 1);

        return $classroom->sessions()->create([
            'title' => $data['title'],
            'order' => $order,
            'note' => $data['note'] ?? null,
            'held_on' => $data['held_on'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClassSession $session, array $data): ClassSession
    {
        $session->fill(array_filter([
            'title' => $data['title'] ?? null,
            'note' => $data['note'] ?? null,
            'held_on' => $data['held_on'] ?? null,
        ], fn ($k) => array_key_exists($k, $data), ARRAY_FILTER_USE_KEY));
        $session->save();

        return $session;
    }

    public function delete(ClassSession $session): void
    {
        $session->delete();
    }

    /**
     * @param  array<int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                ClassSession::where('id', $id)->update(['order' => $index + 1]);
            }
        });
    }

    /**
     * Đồng bộ toàn bộ tiến trình của lớp trong 1 request (tạo/sửa/xoá/đổi thứ tự).
     *
     * @param  array<int, array<string, mixed>>  $sessions
     * @param  array<int>  $deletedIds
     * @param  array<int>  $forceDeleteIds
     * @return array<string, mixed>  ['blocked'=>[...]] nếu bị chặn xoá, ngược lại kết quả sync.
     */
    public function sync(Classroom $classroom, array $sessions, array $deletedIds, array $forceDeleteIds): array
    {
        // Buổi bị xoá mà còn nội dung/nhiệm vụ và KHÔNG được force → chặn lại.
        $blocked = [];
        foreach ($deletedIds as $id) {
            if (in_array($id, $forceDeleteIds, true)) {
                continue;
            }
            $session = $classroom->sessions()->find($id);
            if (! $session) {
                continue;
            }
            $items = SessionItem::where('class_session_id', $id)->count();
            $missions = Mission::where('class_session_id', $id)->count();
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

        $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0];

        DB::transaction(function () use ($classroom, $sessions, $deletedIds, &$counts) {
            // Dùng ClassSession trực tiếp (relation sessions() có orderBy → SQLite không cho UPDATE/DELETE kèm ORDER BY).
            if ($deletedIds) {
                $counts['deleted'] = ClassSession::where('classroom_id', $classroom->id)
                    ->whereIn('id', $deletedIds)->delete();
            }

            foreach ($sessions as $i => $data) {
                $order = $i + 1; // Ép order thành dãy liên tục 1..n theo thứ tự gửi lên.
                if (! empty($data['id'])) {
                    ClassSession::where('classroom_id', $classroom->id)->where('id', $data['id'])->update([
                        'title' => $data['title'],
                        'order' => $order,
                        'is_visible' => $data['is_visible'] ?? true,
                    ]);
                    $counts['updated']++;
                } else {
                    ClassSession::create([
                        'classroom_id' => $classroom->id,
                        'title' => $data['title'],
                        'order' => $order,
                        'is_visible' => $data['is_visible'] ?? true,
                    ]);
                    $counts['created']++;
                }
            }
        });

        return array_merge(
            ['ok' => true, 'sessions' => $this->listForClass($classroom->fresh())->values()],
            $counts,
        );
    }
}
