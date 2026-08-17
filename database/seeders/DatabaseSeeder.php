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
            ['order' => 1, 'term' => 'car', 'meaning' => 'ô tô', 'pos' => 'n.', 'ipa' => '/kɑːr/', 'example' => 'I go to school by *car*.'],
            ['order' => 2, 'term' => 'train', 'meaning' => 'tàu hỏa', 'pos' => 'n.', 'ipa' => '/treɪn/', 'example' => 'I go to school by *train*.'],
            ['order' => 3, 'term' => 'bus', 'meaning' => 'xe buýt', 'pos' => 'n.', 'ipa' => '/bʌs/', 'example' => 'I go to school by *bus*.'],
        ]);

        $animals = Deck::create([
            'owner_id' => $teacher->id,
            'name' => 'Động vật',
            'slug' => 'dong-vat',
            'is_public' => true,
        ]);

        $animals->cards()->createMany([
            ['order' => 1, 'term' => 'dog', 'meaning' => 'con chó', 'pos' => 'n.', 'ipa' => '/dɒg/', 'example' => 'The *dog* is playing in the garden.'],
            ['order' => 2, 'term' => 'cat', 'meaning' => 'con mèo', 'pos' => 'n.', 'ipa' => '/kæt/', 'example' => 'The *cat* is sleeping on the chair.'],
            ['order' => 3, 'term' => 'bird', 'meaning' => 'con chim', 'pos' => 'n.', 'ipa' => '/bɜːrd/', 'example' => 'A *bird* is singing in the tree.'],
        ]);

        $colors = Deck::create([
            'owner_id' => $teacher->id,
            'name' => 'Màu sắc',
            'slug' => 'mau-sac',
            'is_public' => true,
        ]);

        $colors->cards()->createMany([
            ['order' => 1, 'term' => 'red', 'meaning' => 'màu đỏ', 'pos' => 'adj.', 'ipa' => '/red/', 'example' => 'She is wearing a *red* dress.'],
            ['order' => 2, 'term' => 'blue', 'meaning' => 'màu xanh dương', 'pos' => 'adj.', 'ipa' => '/bluː/', 'example' => 'The sky is bright *blue* today.'],
            ['order' => 3, 'term' => 'green', 'meaning' => 'màu xanh lá', 'pos' => 'adj.', 'ipa' => '/griːn/', 'example' => 'The leaves turn *green* in spring.'],
        ]);

        $this->seedSampleTest($teacher);
        $this->seedSampleWritingTest($teacher);
        $this->seedSampleListeningTest($teacher);
        $this->seedSampleSpeakingTest($teacher);
        $this->call(IpaDictionarySeeder::class);
        $this->call(DocumentCategoriesSeeder::class);
        $this->call(VocabularyCategoryDemoSeeder::class);
        $this->call(SystemBrandingSeeder::class);
        $this->call(ArticleDemoSeeder::class);
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

    /**
     * Đề mẫu Writing: 1 Part → 1 Section → 1 câu writing, để test luồng nộp bài → chờ chấm → chấm tay.
     */
    private function seedSampleWritingTest(User $teacher): void
    {
        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Writing: My Hometown',
            'slug' => 'writing-my-hometown',
            'skill' => Skill::Writing,
            'duration_minutes' => 30,
            'total_score' => 10,
            'word_limit' => 150,
            'rubric' => "Tiêu chí chấm:\n- Task achievement (trả lời đúng đề bài): 3đ\n- Coherence & cohesion (bố cục, liên kết câu): 3đ\n- Vocabulary & grammar (từ vựng, ngữ pháp): 4đ",
            'is_published' => true,
        ]);

        $part = $test->parts()->create([
            'order' => 1,
            'title' => 'Part 1',
            'display_mode' => 'default',
        ]);

        $section = $part->sections()->create([
            'order' => 1,
            'instruction' => 'Viết bài luận theo yêu cầu bên dưới.',
        ]);

        $section->questions()->create([
            'order' => 1,
            'type' => QuestionType::Writing,
            'content' => 'Describe your hometown. You should write about its location, what it looks like, '
                .'and why you like or dislike living there. Write at least 150 words.',
            'score' => 10,
        ]);
    }

    /**
     * Đề mẫu Listening: 1 Part → 1 Section có audio_url + max_plays=2, vài câu MCQ trả lời theo audio
     * (máy tự chấm như đề Reading, không cần grading mới).
     */
    private function seedSampleListeningTest(User $teacher): void
    {
        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Listening: Booking a Hotel Room',
            'slug' => 'listening-booking-a-hotel-room',
            'skill' => Skill::Listening,
            'duration_minutes' => 20,
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
            'instruction' => 'Nghe đoạn hội thoại và chọn đáp án đúng. Bạn chỉ được nghe tối đa 2 lần.',
            'audio_url' => '/storage/audio/sample-listening.mp3',
            'max_plays' => 2,
        ]);

        $questions = [
            [
                'content' => 'What type of room does the man want to book?',
                'explanation' => "Người khách nói muốn đặt phòng đôi ('a double room').",
                'options' => [
                    ['label' => 'A', 'content' => 'A single room', 'is_correct' => false],
                    ['label' => 'B', 'content' => 'A double room', 'is_correct' => true],
                    ['label' => 'C', 'content' => 'A suite', 'is_correct' => false],
                ],
            ],
            [
                'content' => 'How many nights will he stay?',
                'explanation' => "Người khách nói sẽ ở lại 'three nights'.",
                'options' => [
                    ['label' => 'A', 'content' => 'Two nights', 'is_correct' => false],
                    ['label' => 'B', 'content' => 'Three nights', 'is_correct' => true],
                    ['label' => 'C', 'content' => 'Four nights', 'is_correct' => false],
                ],
            ],
        ];

        foreach ($questions as $order => $questionData) {
            $question = $section->questions()->create([
                'order' => $order + 1,
                'type' => QuestionType::MultipleChoice,
                'content' => $questionData['content'],
                'explanation' => $questionData['explanation'],
                'score' => 5,
            ]);

            $question->options()->createMany($questionData['options']);
        }
    }

    /**
     * Đề mẫu Speaking: 1 Part → 1 Section → 2 câu speaking (có ảnh gợi ý + giới hạn thời lượng ghi
     * âm), để test luồng thu âm → nộp → chờ chấm → cô chấm tay.
     */
    private function seedSampleSpeakingTest(User $teacher): void
    {
        $test = Test::create([
            'created_by' => $teacher->id,
            'title' => 'Speaking: Describe a Picture',
            'slug' => 'speaking-describe-a-picture',
            'skill' => Skill::Speaking,
            'duration_minutes' => 15,
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
            'instruction' => 'Nhìn tranh và trả lời câu hỏi bằng cách ghi âm.',
        ]);

        $section->questions()->create([
            'order' => 1,
            'type' => QuestionType::Speaking,
            'content' => 'Describe what you see in these pictures.',
            'images' => [
                'https://picsum.photos/seed/speaking-1a/600/400',
                'https://picsum.photos/seed/speaking-1b/600/400',
            ],
            'record_limit_seconds' => 60,
            'score' => 5,
        ]);

        $section->questions()->create([
            'order' => 2,
            'type' => QuestionType::Speaking,
            'content' => 'Do you think this activity is popular in your country? Why or why not?',
            'images' => [
                'https://picsum.photos/seed/speaking-2/600/400',
            ],
            'record_limit_seconds' => 90,
            'score' => 5,
        ]);
    }
}
