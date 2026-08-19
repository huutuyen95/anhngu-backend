<?php

namespace App\Services;

use App\Models\Deck;
use App\Models\Document;
use App\Models\Mission;
use App\Models\Test;
use App\Models\User;
use App\Repositories\MissionRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Nhiệm vụ TỰ ĐẶT của học viên ("Nhiệm vụ 7 ngày tới").
 *
 * Em vào Thư viện, mở một nội dung rồi bấm "Thêm vào nhiệm vụ" → tạo mission với
 * `source='self'` và hạn 7 ngày. Làm xong nội dung đó thì mission chuyển sang
 * "Hoàn thành".
 *
 * KHÁC với bài cô giao (`source='suggested'`): bài cô giao thuộc về "Lớp của em",
 * bị khoá số lượt và KHÔNG hiện ở màn Nhiệm vụ.
 *
 * Mở cho nhiều loại nội dung: mọi thứ có trong `SUPPORTED` đều thêm được, muốn mở
 * thêm loại mới chỉ cần khai một dòng ở đây (khoá phải trùng morph map ở
 * AppServiceProvider) — không phải sửa controller, resource hay bảng dữ liệu.
 */
class StudentMissionService
{
    /** Số ngày em có để hoàn thành một nhiệm vụ tự đặt. */
    public const TARGET_DAYS = 7;

    /**
     * Loại nội dung thêm được vào nhiệm vụ.
     *
     * @var array<string, class-string<Model>>
     */
    public const SUPPORTED = [
        'test' => Test::class,
        'deck' => Deck::class,
        'document' => Document::class,
    ];

    public function __construct(private readonly MissionRepository $missions) {}

    /**
     * @param  'upcoming'|'done'  $tab
     * @return Collection<int, Mission>
     */
    public function list(User $student, string $tab): Collection
    {
        return $this->missions->listSelf($student->id, $tab === 'done');
    }

    /**
     * Thêm một nội dung vào nhiệm vụ, hạn 7 ngày kể từ hôm nay.
     * Thêm lại thứ đang có thì trả về chính nhiệm vụ cũ, không tạo trùng.
     */
    public function add(User $student, string $type, int $id): Mission
    {
        $content = $this->resolveContent($type, $id);
        $existing = $this->missions->findSelfFor($student->id, $content);

        if ($existing) {
            // Đã làm xong mà thêm lại → mở lại thành nhiệm vụ mới với hạn mới.
            return $existing->status === 'done'
                ? $this->missions->update($existing, [
                    'status' => 'todo',
                    'completed_at' => null,
                    'due_date' => $this->target(),
                ])
                : $existing;
        }

        return $this->missions->create([
            'user_id' => $student->id,
            'missionable_type' => $content->getMorphClass(),
            'missionable_id' => $content->getKey(),
            'source' => 'self',
            'status' => 'todo',
            'due_date' => $this->target(),
            // Em tự đặt mục tiêu cho mình thì làm lại bao nhiêu lần cũng được —
            // giới hạn số lượt chỉ dành cho bài cô giao.
            'attempts_allowed' => 0,
        ]);
    }

    /** Gỡ nhiệm vụ khỏi danh sách. Chỉ gỡ được nhiệm vụ tự đặt của chính em. */
    public function remove(User $student, Mission $mission): void
    {
        abort_unless($mission->user_id === $student->id && $mission->source === 'self', 403);

        $this->missions->delete($mission);
    }

    /**
     * Đánh dấu hoàn thành nhiệm vụ tự đặt trỏ tới nội dung này.
     * Gọi khi em nộp bài (xem TestGradingService::submit).
     */
    public function markDone(User $student, Model $content): void
    {
        $this->missions->markSelfDone($student->id, $content);
    }

    /** Nhiệm vụ tự đặt của em cho nội dung này — để nút "Thêm vào nhiệm vụ" biết trạng thái. */
    public function findFor(User $student, Model $content): ?Mission
    {
        return $this->missions->findSelfFor($student->id, $content);
    }

    private function target(): string
    {
        return now()->addDays(self::TARGET_DAYS)->toDateString();
    }

    private function resolveContent(string $type, int $id): Model
    {
        $class = self::SUPPORTED[$type] ?? null;

        if (! $class) {
            throw ValidationException::withMessages([
                'type' => 'Loại nội dung này chưa thêm vào nhiệm vụ được.',
            ]);
        }

        $content = $this->missions->findContent($class, $id);

        if (! $content) {
            throw ValidationException::withMessages([
                'id' => 'Không tìm thấy nội dung này.',
            ]);
        }

        return $content;
    }
}
