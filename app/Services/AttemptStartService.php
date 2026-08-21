<?php

namespace App\Services;

use App\Enums\Skill;
use App\Models\Mission;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use App\Repositories\AttemptRepository;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Mở một lượt làm bài, gắn đúng "nguồn" của lượt đó.
 *
 * Hai nguồn hoàn toàn tách biệt:
 *   - **Bài được giao** (`mission_id` != null): học viên vào từ lớp học. Bị chặn theo
 *     `missions.attempts_allowed`, và kết quả mới được tính vào báo cáo lớp.
 *   - **Tự luyện** (`mission_id` = null): học viên vào từ Thư viện. Làm lại không giới hạn,
 *     KHÔNG đụng tới tiến trình/điểm của lớp.
 *
 * Trước đây cả hai dùng chung một dòng test_attempts nên làm ở thư viện lại tính là đã làm
 * bài giao (và ngược lại, dedup điểm cao nhất xoá mất bài giao).
 */
class AttemptStartService
{
    public function __construct(private readonly AttemptRepository $attempts) {}

    public function start(User $student, Test $test, ?int $missionId): TestAttempt
    {
        $mission = $missionId ? $this->resolveMission($student, $test, $missionId) : null;

        if ($mission) {
            $this->assertAttemptsLeft($mission);
        }

        $this->assertSpeakingQuotaLeft($student, $test, $mission);

        return $this->attempts->transaction(function () use ($student, $test, $mission) {
            // Dọn lượt đang làm dở CÙNG NGUỒN (nếu có). Lượt dở ở nguồn kia phải giữ nguyên —
            // mở đề ở thư viện không được xoá bài đang làm dở của lớp.
            $stale = $this->attempts->staleAttempt($student, $test, $mission);

            if ($stale) {
                $this->attempts->deleteAttemptWithAnswers($stale);
            }

            return $this->attempts->create([
                'user_id' => $student->id,
                'test_id' => $test->id,
                'mission_id' => $mission?->id,
                'classroom_id' => $mission?->classroom_id,
                'source' => $mission ? 'assignment' : 'library',
                'status' => 'in_progress',
                'started_at' => now(),
                // Đồng hồ tính theo SỐ GIÂY CÒN LẠI (dừng khi học viên rời màn làm bài),
                // không phải mốc hết giờ tuyệt đối. Đề không giới hạn giờ thì để null.
                'remaining_seconds' => $test->duration_minutes > 0 ? $test->duration_minutes * 60 : null,
                'resumed_at' => $test->duration_minutes > 0 ? now() : null,
                'question_count' => $this->attempts->questionCount($test),
                // Chụp cấu hình lúc bắt đầu — đổi cấu hình giữa chừng không ảnh hưởng lượt này.
                'config_snapshot' => [
                    'exam.leave_limit' => (int) setting('exam.leave_limit', 3),
                    'exam.leave_action' => setting('exam.leave_action', 'warn'),
                    'exam.autosubmit_on_timeout' => (bool) setting('exam.autosubmit_on_timeout', true),
                    'exam.block_copy' => (bool) setting('exam.block_copy', true),
                    'exam.disable_dictionary' => (bool) setting('exam.disable_dictionary', false),
                    'grading.method' => setting('grading.method', 'scale_10_even'),
                    'grading.decimals' => (int) setting('grading.decimals', 1),
                    'grading.pass_score' => (float) setting('grading.pass_score', 5.0),
                ],
            ]);
        });
    }

    /**
     * Nhiệm vụ phải là của chính em, đúng đề này, và đã tới lượt làm (không phải bản nháp,
     * đã qua giờ hẹn). Sai bất kỳ điều nào → 404: không để lộ mission của người khác.
     */
    private function resolveMission(User $student, Test $test, int $missionId): Mission
    {
        $mission = $this->attempts->findMission($student, $test, $missionId);

        if (! $mission || $mission->status === 'draft') {
            throw new HttpException(404, 'Không tìm thấy nhiệm vụ này.');
        }

        if ($mission->scheduled_at && $mission->scheduled_at->isFuture()) {
            throw ValidationException::withMessages([
                'mission_id' => 'Bài này chưa tới giờ làm.',
            ]);
        }

        return $mission;
    }

    /**
     * Đề NÓI tốn tiền chấm AI (đắt hơn bài viết khoảng 10 lần) nên giới hạn số lượt mỗi
     * ngày cho mỗi học viên.
     *
     * CHỈ áp cho lượt tự mở ở Thư viện / Nhiệm vụ. Bài cô giao đi theo `attempts_allowed`
     * của nhiệm vụ — cô đã chủ động quyết định số lượt thì không chặn thêm.
     */
    private function assertSpeakingQuotaLeft(User $student, Test $test, ?Mission $mission): void
    {
        if ($mission || $test->skill !== Skill::Speaking) {
            return;
        }

        $limit = max(1, (int) setting('content.speaking_attempts_per_day', 3));

        $usedToday = $this->attempts->speakingAttemptsToday($student);

        if ($usedToday >= $limit) {
            throw ValidationException::withMessages([
                'test_id' => "Hôm nay em đã làm {$limit} lượt bài nói rồi. Mai em quay lại luyện tiếp nhé!",
            ]);
        }
    }

    /** Bài giao mặc định chỉ được làm 1 lần — xem Mission::attemptsUsed(). */
    private function assertAttemptsLeft(Mission $mission): void
    {
        if ($this->attempts->missionHasAttemptsLeft($mission)) {
            return;
        }

        $allowed = max(1, (int) ($mission->attempts_allowed ?? 1));

        throw ValidationException::withMessages([
            'mission_id' => "Em đã dùng hết {$allowed} lượt làm bài của nhiệm vụ này.",
        ]);
    }
}
