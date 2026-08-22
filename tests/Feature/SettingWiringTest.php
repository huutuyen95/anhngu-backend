<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Test;
use App\Models\User;
use App\Services\AdminTestService;
use App\Services\DeckService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kiểm tra các giá trị đã chuyển từ hardcode sang đọc cấu hình (setting()).
 */
class SettingWiringTest extends TestCase
{
    use RefreshDatabase;

    private function setSetting(string $key, string $value, string $group): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => 'string', 'group' => $group]);
        app(SettingService::class)->flush();
    }

    public function test_new_deck_uses_default_tts_voice_and_rate_from_settings(): void
    {
        $this->setSetting('tts.default_voice', 'en-US-male', 'system');
        $this->setSetting('tts.default_rate', '1.2', 'system');

        $teacher = User::factory()->teacher()->create();
        $deck = app(DeckService::class)->create(['name' => 'Bộ từ A'], $teacher);

        $this->assertSame('en-US-male', $deck->tts_voice);
        $this->assertSame('1.20', (string) $deck->tts_rate);
    }

    public function test_audio_section_gets_default_max_plays_from_settings(): void
    {
        $this->setSetting('content.listening_max_plays', '4', 'grading');

        $teacher = User::factory()->teacher()->create();
        $test = Test::create([
            'created_by' => $teacher->id, 'title' => 'Đề nghe', 'slug' => 'de-nghe',
            'skill' => 'listening', 'duration_minutes' => 20, 'total_score' => 10, 'is_published' => false,
        ]);

        // Lưu cấu trúc có 1 section audio KHÔNG đặt max_plays → lấy mặc định từ cấu hình.
        app(AdminTestService::class)->saveStructure($test, [
            'parts' => [[
                'order' => 1, 'title' => 'Part 1', 'display_mode' => 'default',
                'sections' => [[
                    'order' => 1, 'audio_url' => 'http://x/a.mp3',
                    'questions' => [],
                ]],
            ]],
        ]);

        $section = $test->fresh(['parts.sections'])->parts->first()->sections->first();
        $this->assertSame(4, (int) $section->max_plays);
    }

    public function test_result_returns_grading_config_from_snapshot(): void
    {
        $this->setSetting('grading.decimals', '2', 'grading');
        $this->setSetting('grading.pass_score', '6.5', 'grading');

        $teacher = User::factory()->teacher()->create();
        $test = Test::create([
            'created_by' => $teacher->id, 'title' => 'T', 'slug' => 't',
            'skill' => 'reading', 'duration_minutes' => 10, 'total_score' => 10, 'is_published' => true,
        ]);
        $part = $test->parts()->create(['order' => 1, 'title' => 'P', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1]);
        $q = $section->questions()->create(['order' => 1, 'type' => 'multiple_choice', 'content' => 'Q', 'score' => 1]);
        $q->options()->createMany([
            ['label' => 'A', 'content' => 'A', 'is_correct' => true],
            ['label' => 'B', 'content' => 'B', 'is_correct' => false],
        ]);

        $student = User::factory()->create();
        $attemptId = $this->actingAs($student)->postJson("/api/v1/tests/{$test->id}/attempts")->json('attempt_id');
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attemptId}/submit")->assertOk();

        $this->actingAs($student)->getJson("/api/v1/attempts/{$attemptId}/result")
            ->assertOk()
            ->assertJsonPath('grading.decimals', 2)
            ->assertJsonPath('grading.pass_score', 6.5);
    }

    public function test_show_explanation_never_hides_answers_on_result(): void
    {
        $teacher = User::factory()->teacher()->create();
        $test = Test::create([
            'created_by' => $teacher->id, 'title' => 'T2', 'slug' => 't2',
            'skill' => 'reading', 'duration_minutes' => 10, 'total_score' => 10, 'is_published' => true,
        ]);
        $part = $test->parts()->create(['order' => 1, 'title' => 'P', 'display_mode' => 'default']);
        $section = $part->sections()->create(['order' => 1]);
        $q = $section->questions()->create(['order' => 1, 'type' => 'multiple_choice', 'content' => 'Q', 'score' => 1]);
        $q->options()->createMany([
            ['label' => 'A', 'content' => 'A', 'is_correct' => true],
            ['label' => 'B', 'content' => 'B', 'is_correct' => false],
        ]);
        $student = User::factory()->create();

        // Mặc định (after_submit) → lộ đáp án + lời giải.
        $attempt1 = $this->actingAs($student)->postJson("/api/v1/tests/{$test->id}/attempts")->json('attempt_id');
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attempt1}/submit");
        $reveal = json_encode($this->actingAs($student)->getJson("/api/v1/attempts/{$attempt1}/result")->json('test'));
        $this->assertStringContainsString('is_correct', $reveal);

        // never → không lộ đáp án đúng.
        $this->setSetting('grading.show_explanation', 'never', 'grading');
        $attempt2 = $this->actingAs($student)->postJson("/api/v1/tests/{$test->id}/attempts")->json('attempt_id');
        $this->actingAs($student)->postJson("/api/v1/attempts/{$attempt2}/submit");
        $hidden = json_encode($this->actingAs($student)->getJson("/api/v1/attempts/{$attempt2}/result")->json('test'));
        $this->assertStringNotContainsString('is_correct', $hidden);
    }
}
