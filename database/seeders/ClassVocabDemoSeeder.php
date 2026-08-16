<?php

namespace Database\Seeders;

use App\Models\Classroom;
use App\Models\Deck;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed demo học từ vựng TRONG LỚP cho hs2@example.com: 1 buổi có 2 bộ từ vựng (nhiều nhóm).
 * Idempotent — chạy nhiều lần không nhân đôi.
 */
class ClassVocabDemoSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::where('email', 'hs2@example.com')->first();
        if (! $student) {
            $this->command?->warn('Không có hs2@example.com — bỏ qua ClassVocabDemoSeeder.');

            return;
        }

        $classroom = $student->classes()->first();
        if (! $classroom) {
            $classroom = Classroom::firstOrCreate(
                ['slug' => 'lop-demo-tu-vung'],
                ['name' => 'Lớp Demo Từ Vựng', 'teacher_id' => User::where('role', 'teacher')->value('id'), 'is_active' => true],
            );
            $classroom->students()->syncWithoutDetaching([$student->id => ['status' => 'studying']]);
        }
        $teacherId = $classroom->teacher_id ?? User::where('role', 'teacher')->value('id');

        $session = $classroom->sessions()->firstOrCreate(
            ['title' => 'Buổi 1 · Vocabulary'],
            ['order' => 0, 'is_visible' => true],
        );

        $decks = [
            'GRADE 10 UNIT 5' => [
                ['souvenir', 'quà lưu niệm', "/ˌsuːvəˈnɪr/", 'I bought a *souvenir* from Hanoi.'],
                ['invent', 'phát minh', "/ɪnˈvent/", 'Edison *invent*ed the light bulb.'],
                ['improve', 'cải thiện', "/ɪmˈpruːv/", 'Reading helps you *improve* your English.'],
                ['useful', 'hữu ích', "/ˈjuːsfl/", 'This app is very *useful* for students.'],
                ['save', 'tiết kiệm', "/seɪv/", 'You should *save* money every month.'],
            ],
            'GRADE 7 UNIT 4' => [
                ['festival', 'lễ hội', "/ˈfestɪvl/", 'Tet is the biggest *festival* in Vietnam.'],
                ['tradition', 'truyền thống', "/trəˈdɪʃn/", 'Wrapping banh chung is a *tradition*.'],
                ['celebrate', 'ăn mừng', "/ˈselɪbreɪt/", 'We *celebrate* the new year together.'],
            ],
        ];

        foreach ($decks as $name => $cards) {
            $deck = Deck::firstOrCreate(
                ['slug' => Str::slug($name).'-inclass'],
                ['owner_id' => $teacherId, 'name' => $name, 'is_public' => true, 'is_published' => true],
            );

            if ($deck->cards()->count() === 0) {
                foreach ($cards as $i => [$term, $meaning, $ipa, $example]) {
                    $deck->cards()->create([
                        'order' => $i, 'term' => $term, 'meaning' => $meaning, 'ipa' => $ipa,
                        'pos' => null, 'example' => $example,
                    ]);
                }
            }

            Mission::firstOrCreate(
                [
                    'user_id' => $student->id,
                    'classroom_id' => $classroom->id,
                    'missionable_type' => $deck->getMorphClass(),
                    'missionable_id' => $deck->id,
                ],
                [
                    'assigned_by' => $teacherId,
                    'class_session_id' => $session->id,
                    'source' => 'suggested',
                    'status' => 'todo',
                    'attempts_allowed' => 1,
                ],
            );
        }

        $this->command?->info("Seeded 2 bộ từ trong lớp #{$classroom->id} / buổi #{$session->id} cho {$student->email}.");
    }
}
