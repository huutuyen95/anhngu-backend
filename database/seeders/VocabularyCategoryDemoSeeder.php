<?php

namespace Database\Seeders;

use App\Models\Deck;
use App\Models\DeckCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class VocabularyCategoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::query()->whereIn('role', ['teacher', 'admin'])->firstOrFail();

        $categories = collect([
            ['name' => 'Từ vựng theo lớp', 'order' => 1],
            ['name' => 'Chủ đề hằng ngày', 'order' => 2],
            ['name' => 'Cụm từ & Collocation', 'order' => 3],
        ])->mapWithKeys(function (array $data) {
            $category = DeckCategory::updateOrCreate(['name' => $data['name']], ['order' => $data['order']]);

            return [$data['name'] => $category];
        });

        $samples = [
            [
                'slug' => 'demo-grade-8-unit-5',
                'name' => 'GRADE 8 UNIT 5',
                'category' => 'Từ vựng theo lớp',
                'cards' => [
                    ['term' => 'community', 'meaning' => 'cộng đồng', 'pos' => 'n.', 'ipa' => '/kəˈmjuːnəti/', 'example' => 'Our *community* works together to keep the park clean.'],
                    ['term' => 'volunteer', 'meaning' => 'tình nguyện viên', 'pos' => 'n.', 'ipa' => '/ˌvɒlənˈtɪə(r)/', 'example' => 'Each *volunteer* helps children with their homework.'],
                    ['term' => 'donate', 'meaning' => 'quyên góp', 'pos' => 'v.', 'ipa' => '/dəʊˈneɪt/', 'example' => 'We *donate* books to the local library every year.'],
                    ['term' => 'elderly', 'meaning' => 'người cao tuổi', 'pos' => 'adj.', 'ipa' => '/ˈeldəli/', 'example' => 'The students visit *elderly* people at the weekend.'],
                    ['term' => 'campaign', 'meaning' => 'chiến dịch', 'pos' => 'n.', 'ipa' => '/kæmˈpeɪn/', 'example' => 'Our school started a recycling *campaign*.'],
                ],
            ],
            [
                'slug' => 'demo-daily-communication',
                'name' => 'GIAO TIẾP HẰNG NGÀY',
                'category' => 'Chủ đề hằng ngày',
                'cards' => [
                    ['term' => 'How are you?', 'meaning' => 'Bạn khỏe không?', 'pos' => 'phr.', 'ipa' => '/haʊ ɑː juː/', 'example' => '*How are you?* — I am fine, thank you.'],
                    ['term' => 'See you soon', 'meaning' => 'Hẹn sớm gặp lại', 'pos' => 'phr.', 'ipa' => '/siː juː suːn/', 'example' => 'I have to go now. *See you soon*!'],
                    ['term' => 'Take care', 'meaning' => 'Giữ gìn sức khỏe nhé', 'pos' => 'phr.', 'ipa' => '/teɪk keə(r)/', 'example' => '*Take care* on your way home.'],
                    ['term' => 'No problem', 'meaning' => 'Không có vấn đề gì', 'pos' => 'phr.', 'ipa' => '/nəʊ ˈprɒbləm/', 'example' => 'Thanks for your help. — *No problem*.'],
                    ['term' => 'Sounds good', 'meaning' => 'Nghe hay đấy', 'pos' => 'phr.', 'ipa' => '/saʊndz ɡʊd/', 'example' => 'Let us meet at seven. — *Sounds good*.'],
                ],
            ],
            [
                'slug' => 'demo-common-collocations',
                'name' => 'CỤM COLLOCATION THÔNG DỤNG',
                'category' => 'Cụm từ & Collocation',
                'cards' => [
                    ['term' => 'make a decision', 'meaning' => 'đưa ra quyết định', 'pos' => 'phr.', 'ipa' => '/meɪk ə dɪˈsɪʒn/', 'example' => 'I need to *make a decision* before Friday.'],
                    ['term' => 'take a break', 'meaning' => 'nghỉ giải lao', 'pos' => 'phr.', 'ipa' => '/teɪk ə breɪk/', 'example' => 'Let us *take a break* after this exercise.'],
                    ['term' => 'pay attention', 'meaning' => 'chú ý', 'pos' => 'phr.', 'ipa' => '/peɪ əˈtenʃn/', 'example' => 'Please *pay attention* to the teacher.'],
                    ['term' => 'keep in touch', 'meaning' => 'giữ liên lạc', 'pos' => 'phr.', 'ipa' => '/kiːp ɪn tʌtʃ/', 'example' => 'We still *keep in touch* after leaving school.'],
                    ['term' => 'catch a cold', 'meaning' => 'bị cảm lạnh', 'pos' => 'phr.', 'ipa' => '/kætʃ ə kəʊld/', 'example' => 'Wear a warm coat or you may *catch a cold*.'],
                ],
            ],
        ];

        foreach ($samples as $sample) {
            $deck = Deck::updateOrCreate(
                ['slug' => $sample['slug']],
                [
                    'owner_id' => $teacher->id,
                    'category_id' => $categories[$sample['category']]->id,
                    'name' => $sample['name'],
                    'description' => 'Dữ liệu mẫu để kiểm tra bộ lọc danh mục ở Thư viện.',
                    'is_public' => true,
                    'is_published' => true,
                ],
            );

            foreach ($sample['cards'] as $index => $card) {
                $deck->cards()->updateOrCreate(
                    ['term' => $card['term']],
                    [
                        'order' => $index + 1,
                        'meaning' => $card['meaning'],
                        'pos' => $card['pos'],
                        'ipa' => $card['ipa'],
                        'example' => $card['example'],
                    ],
                );
            }
        }
    }
}
