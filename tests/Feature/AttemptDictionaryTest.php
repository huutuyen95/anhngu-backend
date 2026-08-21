<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\IpaEntry;
use App\Models\Mission;
use App\Models\Setting;
use App\Models\Test;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tra từ điển khi bôi đen trong lúc làm bài.
 *
 * Ba nơi em làm bài, phân biệt bằng `test_attempts.classroom_id`:
 *   - Thư viện (tự luyện)          → CHO tra
 *   - Nhiệm vụ em tự thêm          → CHO tra
 *   - Bài cô giao ở lớp            → CẤM (tính như bài kiểm tra, em phải tự làm)
 */
class AttemptDictionaryTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Test $test;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->create();

        $this->test = Test::create([
            'created_by' => $this->teacher->id, 'title' => 'Đề đọc', 'slug' => 'de-doc-'.uniqid(),
            'skill' => 'reading', 'duration_minutes' => 30, 'total_score' => 10, 'is_published' => true,
        ]);

        $part = $this->test->parts()->create(['title' => 'Part 1', 'order' => 1]);
        $section = $part->sections()->create(['order' => 1]);
        $question = $section->questions()->create([
            'type' => 'multiple_choice', 'content' => 'Chọn đáp án', 'order' => 1, 'score' => 1,
        ]);
        $question->options()->create(['content' => 'A', 'is_correct' => true, 'order' => 1]);
        $question->options()->create(['content' => 'B', 'is_correct' => false, 'order' => 2]);
    }

    /** Mở lượt làm bài rồi đọc cờ cho phép tra từ. */
    private function dictionaryEnabledFor(?int $missionId): bool
    {
        $attemptId = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts", array_filter(['mission_id' => $missionId]))
            ->assertOk()->json('attempt_id');

        return (bool) $this->actingAs($this->student)
            ->getJson("/api/v1/attempts/{$attemptId}")->assertOk()->json('dictionary_enabled');
    }

    /** Nhiệm vụ em TỰ THÊM từ Thư viện — không gắn lớp nào. */
    private function selfMission(): Mission
    {
        return Mission::create([
            'user_id' => $this->student->id,
            'missionable_type' => $this->test->getMorphClass(),
            'missionable_id' => $this->test->id,
            'source' => 'self', 'status' => 'todo', 'attempts_allowed' => 0,
        ]);
    }

    /** Bài CÔ GIAO trong lớp — có classroom_id. */
    private function classroomMission(): Mission
    {
        $classroom = Classroom::create([
            'teacher_id' => $this->teacher->id, 'name' => 'Lớp 9A', 'slug' => 'lop-9a', 'is_active' => true,
        ]);
        $classroom->students()->attach($this->student->id, ['status' => 'studying']);
        $session = $classroom->sessions()->create(['title' => 'Buổi 1', 'order' => 1, 'is_visible' => true]);

        return Mission::create([
            'user_id' => $this->student->id, 'assigned_by' => $this->teacher->id,
            'classroom_id' => $classroom->id, 'class_session_id' => $session->id,
            'missionable_type' => $this->test->getMorphClass(),
            'missionable_id' => $this->test->id,
            'source' => 'suggested', 'status' => 'todo', 'attempts_allowed' => 5,
        ]);
    }

    public function test_dictionary_is_allowed_when_practising_from_the_library(): void
    {
        $this->assertTrue($this->dictionaryEnabledFor(null));
    }

    public function test_dictionary_is_allowed_for_a_mission_the_student_added(): void
    {
        $this->assertTrue($this->dictionaryEnabledFor($this->selfMission()->id));
    }

    public function test_dictionary_is_blocked_for_work_assigned_in_class(): void
    {
        $this->assertFalse($this->dictionaryEnabledFor($this->classroomMission()->id));
    }

    /**
     * Cô siết thêm ở Cài đặt → cấm luôn cả tự luyện. Nhưng bài giao ở lớp thì vốn đã cấm,
     * cài đặt không mở ra được.
     */
    public function test_teacher_can_also_switch_it_off_for_self_practice(): void
    {
        app(SettingService::class)->set(['exam.disable_dictionary' => true]);

        $this->assertFalse($this->dictionaryEnabledFor(null));
        $this->assertFalse($this->dictionaryEnabledFor($this->classroomMission()->id));
    }

    /** Cấm ở lớp là quy tắc CỨNG — tắt cài đặt cũng không mở ra được. */
    public function test_classroom_ban_cannot_be_overridden_by_settings(): void
    {
        Setting::updateOrCreate(['key' => 'exam.disable_dictionary'], [
            'value' => '0', 'type' => 'bool', 'group' => 'exam',
        ]);
        app(SettingService::class)->flush();

        $this->assertFalse($this->dictionaryEnabledFor($this->classroomMission()->id));
    }

    /**
     * Đổi cài đặt giữa chừng KHÔNG được đổi luật của lượt đang làm dở — cùng nguyên tắc
     * với số lần rời tab và chặn sao chép (đều đọc từ `config_snapshot`).
     */
    public function test_changing_the_setting_does_not_affect_an_attempt_in_progress(): void
    {
        $attemptId = $this->actingAs($this->student)
            ->postJson("/api/v1/tests/{$this->test->id}/attempts")->assertOk()->json('attempt_id');

        app(SettingService::class)->set(['exam.disable_dictionary' => true]);

        $this->actingAs($this->student)
            ->getJson("/api/v1/attempts/{$attemptId}")->assertOk()
            ->assertJsonPath('dictionary_enabled', true);
    }

    /* ── Tra từ ─────────────────────────────────────────────────────────────── */

    public function test_lookup_returns_meaning_and_phonetic_without_slashes(): void
    {
        // Hai kiểu ghi lẫn lộn trong DB: bộ soạn tay có dấu /…/, bộ nạp máy thì không.
        IpaEntry::create(['word' => 'apple', 'ipa' => '/ˈæp.əl/', 'pos' => 'n.', 'meaning_vi' => 'quả táo']);
        IpaEntry::create(['word' => 'think', 'ipa' => 'ˈθɪŋk', 'pos' => 'v.', 'meaning_vi' => 'nghĩ']);

        foreach ([['apple', 'ˈæp.əl', 'quả táo'], ['think', 'ˈθɪŋk', 'nghĩ']] as [$word, $ipa, $meaning]) {
            $this->actingAs($this->student)
                ->getJson("/api/v1/dictionary?word={$word}")->assertOk()
                ->assertJsonPath('ipa', $ipa)          // KHÔNG kèm dấu /
                ->assertJsonPath('meaning_vi', $meaning);
        }
    }

    /** Bôi đen từ đã chia thì vẫn tra được nhờ đưa về dạng gốc. */
    public function test_lookup_falls_back_to_the_base_form(): void
    {
        IpaEntry::create(['word' => 'go', 'ipa' => 'ɡəʊ', 'pos' => 'v.', 'meaning_vi' => 'đi']);

        $this->actingAs($this->student)
            ->getJson('/api/v1/dictionary?word=went')->assertOk()
            ->assertJsonPath('word', 'go')
            ->assertJsonPath('matched_from', 'went');
    }
}
