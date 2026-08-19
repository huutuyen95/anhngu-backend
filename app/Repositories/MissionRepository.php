<?php

namespace App\Repositories;

use App\Models\Mission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Truy cập dữ liệu cho nhiệm vụ tự đặt của học viên (`missions.source = 'self'`).
 * Bài cô giao đi qua AssignmentRepository, không dùng chỗ này.
 */
class MissionRepository
{
    /**
     * @return Collection<int, Mission>
     */
    public function listSelf(int $userId, bool $done): Collection
    {
        return Mission::query()
            ->where('user_id', $userId)
            ->where('source', 'self')
            ->when(
                $done,
                fn ($q) => $q->where('status', 'done')->orderByDesc('completed_at'),
                // Chưa xong: hạn gần nhất lên trước. Quá hạn vẫn giữ lại để em còn thấy
                // mà làm nốt — không tự dọn đồ của em.
                fn ($q) => $q->where('status', '!=', 'done')->orderBy('due_date'),
            )
            ->with('missionable')
            ->get();
    }

    public function findSelfFor(int $userId, Model $content): ?Mission
    {
        return Mission::query()
            ->where('user_id', $userId)
            ->where('source', 'self')
            ->where('missionable_type', $content->getMorphClass())
            ->where('missionable_id', $content->getKey())
            ->with('missionable')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Mission
    {
        return Mission::create($data)->load('missionable');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Mission $mission, array $data): Mission
    {
        $mission->update($data);

        return $mission->load('missionable');
    }

    public function delete(Mission $mission): void
    {
        $mission->delete();
    }

    /** Đánh dấu xong mọi nhiệm vụ tự đặt của em trỏ tới nội dung này. */
    public function markSelfDone(int $userId, Model $content): void
    {
        Mission::query()
            ->where('user_id', $userId)
            ->where('source', 'self')
            ->where('missionable_type', $content->getMorphClass())
            ->where('missionable_id', $content->getKey())
            ->where('status', '!=', 'done')
            ->update(['status' => 'done', 'completed_at' => now()]);
    }

    public function findContent(string $class, int $id): ?Model
    {
        return $class::find($id);
    }
}
