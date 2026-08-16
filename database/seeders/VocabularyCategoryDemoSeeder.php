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
                    ['term' => 'community', 'meaning' => 'cộng đồng'],
                    ['term' => 'volunteer', 'meaning' => 'tình nguyện viên'],
                    ['term' => 'donate', 'meaning' => 'quyên góp'],
                    ['term' => 'elderly', 'meaning' => 'người cao tuổi'],
                    ['term' => 'campaign', 'meaning' => 'chiến dịch'],
                ],
            ],
            [
                'slug' => 'demo-daily-communication',
                'name' => 'GIAO TIẾP HẰNG NGÀY',
                'category' => 'Chủ đề hằng ngày',
                'cards' => [
                    ['term' => 'How are you?', 'meaning' => 'Bạn khỏe không?'],
                    ['term' => 'See you soon', 'meaning' => 'Hẹn sớm gặp lại'],
                    ['term' => 'Take care', 'meaning' => 'Giữ gìn sức khỏe nhé'],
                    ['term' => 'No problem', 'meaning' => 'Không có vấn đề gì'],
                    ['term' => 'Sounds good', 'meaning' => 'Nghe hay đấy'],
                ],
            ],
            [
                'slug' => 'demo-common-collocations',
                'name' => 'CỤM COLLOCATION THÔNG DỤNG',
                'category' => 'Cụm từ & Collocation',
                'cards' => [
                    ['term' => 'make a decision', 'meaning' => 'đưa ra quyết định'],
                    ['term' => 'take a break', 'meaning' => 'nghỉ giải lao'],
                    ['term' => 'pay attention', 'meaning' => 'chú ý'],
                    ['term' => 'keep in touch', 'meaning' => 'giữ liên lạc'],
                    ['term' => 'catch a cold', 'meaning' => 'bị cảm lạnh'],
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
                    ['order' => $index + 1, 'meaning' => $card['meaning']],
                );
            }
        }
    }
}
