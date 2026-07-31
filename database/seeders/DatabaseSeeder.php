<?php

namespace Database\Seeders;

use App\Enums\QuestionType;
use App\Enums\Skill;
use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Deck;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $teacher = User::create([
            'name' => 'Cô giáo',
            'email' => 'teacher@example.com',
            'password' => Hash::make('Admin@123'),
            'role' => UserRole::Teacher,
        ]);

        $student = User::create([
            'name' => 'Học sinh',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::Student,
        ]);

        $classroom = Classroom::create([
            'teacher_id' => $teacher->id,
            'name' => 'Lớp 12F',
            'slug' => 'lop-12f',
            'is_active' => true,
        ]);

        $classroom->students()->attach($student->id, ['status' => 'studying']);

        $vehicles = Deck::create([
            'owner_id' => $teacher->id,
            'name' => 'Phương tiện',
            'slug' => 'phuong-tien',
            'is_public' => true,
        ]);

        $vehicles->cards()->createMany([
            ['order' => 1, 'term' => 'car', 'meaning' => 'ô tô', 'ipa' => '/kɑːr/'],
            ['order' => 2, 'term' => 'train', 'meaning' => 'tàu hỏa', 'ipa' => '/treɪn/'],
            ['order' => 3, 'term' => 'bus', 'meaning' => 'xe buýt', 'ipa' => '/bʌs/'],
        ]);

        $animals = Deck::create([
            'owner_id' => $teacher->id,
            'name' => 'Động vật',
            'slug' => 'dong-vat',
            'is_public' => true,
        ]);

        $animals->cards()->createMany([
            ['order' => 1, 'term' => 'dog', 'meaning' => 'con chó', 'ipa' => '/dɒg/'],
            ['order' => 2, 'term' => 'cat', 'meaning' => 'con mèo', 'ipa' => '/kæt/'],
            ['order' => 3, 'term' => 'bird', 'meaning' => 'con chim', 'ipa' => '/bɜːrd/'],
        ]);

        $colors = Deck::create([
            'owner_id' => $teacher->id,
            'name' => 'Màu sắc',
            'slug' => 'mau-sac',
            'is_public' => true,
        ]);

        $colors->cards()->createMany([
            ['order' => 1, 'term' => 'red', 'meaning' => 'màu đỏ', 'ipa' => '/red/'],
            ['order' => 2, 'term' => 'blue', 'meaning' => 'màu xanh dương', 'ipa' => '/bluː/'],
            ['order' => 3, 'term' => 'green', 'meaning' => 'màu xanh lá', 'ipa' => '/griːn/'],
        ]);

        $this->seedSampleTest($teacher);

        $this->call(IpaDictionarySeeder::class);
    }

    /**
     * Đề mẫu Reading: 1 Part → 1 Section, đủ 3 loại câu tự chấm (Sprint 2).
     */
    private function seedSampleTest(User $teacher): void
    {
        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Reading: Lake Baikal',
            'slug' => 'reading-lake-baikal',
            'skill' => Skill::Reading,
            'duration_minutes' => 30,
            'total_score' => 10,
            'is_published' => true,
        ]);

        $part = $test->parts()->create([
            'order' => 1,
            'title' => 'Part 1',
            'display_mode' => 'default',
        ]);

        $section = $part->sections()->create([
            'order' => 1,
            'instruction' => 'Chọn đáp án đúng',
            'passage' => "Lake Baikal is located in Siberia, Russia. It is the oldest and deepest freshwater lake in the world, formed about 25 million years ago. The lake holds around 20% of the world's unfrozen fresh water. Many unique species, such as the Baikal seal, live only in this lake. In winter, the surface of the lake freezes completely and becomes so clear that people can see many meters below the ice. Local people call the lake the 'Pearl of Siberia' because of its natural beauty. Every year, thousands of tourists travel to Lake Baikal to see its clear water and diverse wildlife.",
        ]);

        $questions = [
            [
                'type' => QuestionType::MultipleChoice,
                'content' => 'Lake Baikal is located in ____.',
                'explanation' => 'Theo câu đầu đoạn văn, hồ Baikal nằm ở Siberia, Russia.',
                'options' => [
                    ['label' => 'A', 'content' => 'Siberia, Russia', 'is_correct' => true],
                    ['label' => 'B', 'content' => 'Siberia, China', 'is_correct' => false],
                    ['label' => 'C', 'content' => 'Mongolia', 'is_correct' => false],
                    ['label' => 'D', 'content' => 'Kazakhstan', 'is_correct' => false],
                ],
            ],
            [
                'type' => QuestionType::MultipleChoice,
                'content' => 'How old is Lake Baikal approximately?',
                'explanation' => 'Đoạn văn nêu rõ hồ được hình thành khoảng 25 triệu năm trước.',
                'options' => [
                    ['label' => 'A', 'content' => '2.5 million years', 'is_correct' => false],
                    ['label' => 'B', 'content' => '25 million years', 'is_correct' => true],
                    ['label' => 'C', 'content' => '250 million years', 'is_correct' => false],
                    ['label' => 'D', 'content' => '25,000 years', 'is_correct' => false],
                ],
            ],
            [
                'type' => QuestionType::MultipleChoice,
                'content' => "What percentage of the world's unfrozen fresh water does Lake Baikal hold?",
                'explanation' => 'Đoạn văn nói hồ chiếm khoảng 20% lượng nước ngọt chưa đóng băng của thế giới.',
                'options' => [
                    ['label' => 'A', 'content' => '10%', 'is_correct' => false],
                    ['label' => 'B', 'content' => '15%', 'is_correct' => false],
                    ['label' => 'C', 'content' => '20%', 'is_correct' => true],
                    ['label' => 'D', 'content' => '25%', 'is_correct' => false],
                ],
            ],
            [
                'type' => QuestionType::Select,
                'content' => 'The Baikal seal can be found in many other lakes around the world.',
                'explanation' => "Đoạn văn nói loài hải cẩu Baikal chỉ sống ở hồ này ('live only in this lake') nên câu này SAI.",
                'options' => [
                    ['label' => null, 'content' => 'True', 'is_correct' => false],
                    ['label' => null, 'content' => 'False', 'is_correct' => true],
                    ['label' => null, 'content' => 'Not Given', 'is_correct' => false],
                ],
            ],
            [
                'type' => QuestionType::Select,
                'content' => "In winter, the lake's surface becomes completely frozen.",
                'explanation' => 'Đoạn văn nói vào mùa đông, bề mặt hồ đóng băng hoàn toàn nên câu này ĐÚNG.',
                'options' => [
                    ['label' => null, 'content' => 'True', 'is_correct' => true],
                    ['label' => null, 'content' => 'False', 'is_correct' => false],
                    ['label' => null, 'content' => 'Not Given', 'is_correct' => false],
                ],
            ],
            [
                'type' => QuestionType::FillBlank,
                'content' => 'Every year, thousands of ____ travel to Lake Baikal to see its clear water and wildlife.',
                'explanation' => "Đoạn văn dùng từ 'tourists' (có thể chấp nhận từ đồng nghĩa 'visitors').",
                'options' => [
                    ['label' => null, 'content' => 'tourists', 'is_correct' => true],
                    ['label' => null, 'content' => 'visitors', 'is_correct' => true],
                ],
            ],
            [
                'type' => QuestionType::FillBlank,
                'content' => "Local people call the lake the '____' of Siberia.",
                'explanation' => "Đoạn văn gọi hồ là 'Pearl of Siberia' (viên ngọc trai của Siberia).",
                'options' => [
                    ['label' => null, 'content' => 'Pearl', 'is_correct' => true],
                    ['label' => null, 'content' => 'pearl', 'is_correct' => true],
                ],
            ],
        ];

        foreach ($questions as $order => $questionData) {
            $question = $section->questions()->create([
                'order' => $order + 1,
                'type' => $questionData['type'],
                'content' => $questionData['content'],
                'explanation' => $questionData['explanation'],
                'score' => 1,
            ]);

            $question->options()->createMany($questionData['options']);
        }
    }
}
