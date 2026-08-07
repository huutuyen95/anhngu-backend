<?php

namespace Tests\Feature;

use App\Enums\Skill;
use App\Models\AttemptSkillScore;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `tests.skill` và `attempt_skill_scores.skill` đã chuyển enum → string
 * (2026_08_07_110000) để thêm kỹ năng mới không phải ALTER MODIFY.
 */
class SkillColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_skill_case_round_trips(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        foreach (Skill::cases() as $case) {
            $test = Test::create([
                'created_by' => $teacher->id,
                'title' => 'Đề '.$case->value,
                'slug' => 'de-'.$case->value,
                'skill' => $case->value,
                'is_published' => true,
            ]);

            $this->assertSame($case, $test->fresh()->skill);
        }
    }

    /** Cột không còn ràng buộc enum ở DB — thêm case mới chỉ cần sửa App\Enums\Skill. */
    public function test_database_accepts_a_value_outside_the_current_enum(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $id = DB::table('tests')->insertGetId([
            'created_by' => $teacher->id,
            'title' => 'Đề từ vựng',
            'slug' => 'de-tu-vung',
            'skill' => 'vocabulary',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('vocabulary', DB::table('tests')->where('id', $id)->value('skill'));
    }

    /** change() trên SQLite dựng lại bảng — unique(['test_attempt_id','skill']) phải còn. */
    public function test_attempt_skill_scores_keeps_its_unique_constraint(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Đề combo',
            'slug' => 'de-combo',
            'skill' => 'mixed',
            'is_combo' => true,
            'is_published' => true,
        ]);

        $attempt = TestAttempt::create([
            'user_id' => $student->id,
            'test_id' => $test->id,
            'status' => 'submitted',
            'started_at' => now(),
        ]);

        AttemptSkillScore::create([
            'test_attempt_id' => $attempt->id,
            'skill' => 'reading',
            'score' => 8.0,
        ]);

        // Khác kỹ năng trên cùng attempt → vẫn ghi được.
        AttemptSkillScore::create([
            'test_attempt_id' => $attempt->id,
            'skill' => 'listening',
            'score' => 7.0,
        ]);

        $this->assertSame(2, AttemptSkillScore::where('test_attempt_id', $attempt->id)->count());

        // Trùng (attempt, skill) → phải bị chặn.
        $this->expectException(QueryException::class);
        AttemptSkillScore::create([
            'test_attempt_id' => $attempt->id,
            'skill' => 'reading',
            'score' => 9.0,
        ]);
    }
}
