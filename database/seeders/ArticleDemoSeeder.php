<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ArticleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::query()->whereIn('role', ['teacher', 'admin'])->firstOrFail();

        $categories = collect(['Giải trí', 'Ôn luyện', 'Tin tức về IELTS'])
            ->mapWithKeys(fn (string $name, int $index) => [
                $name => ArticleCategory::firstOrCreate(['name' => $name], ['order' => $index + 1]),
            ]);

        $samples = [
            [
                'slug' => 'cac-su-kien-hoi-thao-ielts',
                'title' => 'Các sự kiện, hội thảo IELTS',
                'category' => 'Tin tức về IELTS',
                'image' => 'article-ielts-events.png',
                'excerpt' => 'Tổng hợp các hội thảo trực tuyến và hoạt động hữu ích dành cho người học IELTS.',
                'body' => '<h2>Chuỗi hội thảo IELTS trực tuyến</h2><p>Các buổi hội thảo giúp em hiểu rõ tiêu chí đánh giá và xây dựng lộ trình ôn tập hiệu quả.</p><h2>Em sẽ nhận được gì?</h2><ul><li>Kinh nghiệm cải thiện điểm số theo từng kỹ năng.</li><li>Những lỗi thường gặp trong quá trình làm bài.</li><li>Cách xây dựng kế hoạch học phù hợp với mục tiêu.</li></ul>',
            ],
            [
                'slug' => 'du-hoc-anh-can-ielts-bao-nhieu',
                'title' => 'Du học Anh cần IELTS bao nhiêu?',
                'category' => 'Giải trí',
                'image' => 'article-study-abroad.png',
                'excerpt' => 'Mức điểm IELTS phổ biến và những điều cần chuẩn bị khi có kế hoạch du học Anh.',
                'body' => '<h2>Mức điểm IELTS phổ biến</h2><p>Phần lớn chương trình yêu cầu IELTS từ 6.0 đến 7.0, tuỳ trường và ngành học.</p><h2>Cách chuẩn bị</h2><p>Hãy kiểm tra yêu cầu chính thức của trường, xác định kỹ năng cần cải thiện và dành thời gian luyện đề đều đặn.</p>',
            ],
            [
                'slug' => 'ielts-6-la-cao-hay-thap',
                'title' => 'IELTS 6.0 là cao hay thấp và làm được gì?',
                'category' => 'Ôn luyện',
                'image' => 'article-ielts-score.png',
                'excerpt' => 'Hiểu ý nghĩa của band 6.0 và cách tiếp tục nâng điểm IELTS của em.',
                'body' => '<h2>Band 6.0 có ý nghĩa gì?</h2><p>IELTS 6.0 cho thấy em có thể sử dụng tiếng Anh tương đối hiệu quả trong nhiều tình huống quen thuộc.</p><h2>Hướng tới band cao hơn</h2><ul><li>Mở rộng vốn từ theo chủ đề.</li><li>Ghi lại lỗi sai sau mỗi bài luyện.</li><li>Luyện nghe và đọc hằng ngày.</li></ul>',
            ],
            [
                'slug' => 'dang-ky-thi-thu-ielts',
                'title' => 'Cách chuẩn bị cho một bài thi thử IELTS',
                'category' => 'Tin tức về IELTS',
                'image' => 'article-mock-test.png',
                'excerpt' => 'Những bước đơn giản giúp em tận dụng tốt một lần thi thử IELTS.',
                'body' => '<h2>Trước ngày thi thử</h2><p>Chuẩn bị dụng cụ, ngủ đủ giấc và xem lại cấu trúc của bốn kỹ năng.</p><h2>Sau khi có kết quả</h2><p>Đừng chỉ nhìn tổng điểm. Hãy phân tích từng lỗi sai và chọn một mục tiêu nhỏ cho tuần học tiếp theo.</p>',
            ],
        ];

        foreach ($samples as $index => $sample) {
            $source = database_path('seeders/assets/articles/'.$sample['image']);
            if (! is_file($source)) {
                throw new RuntimeException("Không tìm thấy ảnh bài viết: {$source}");
            }

            $path = 'articles/'.$sample['image'];
            Storage::disk('public')->put($path, file_get_contents($source));

            Article::updateOrCreate(
                ['slug' => $sample['slug']],
                [
                    'title' => $sample['title'],
                    'category_id' => $categories[$sample['category']]->id,
                    'thumbnail_url' => asset('storage/'.$path),
                    'excerpt' => $sample['excerpt'],
                    'body' => $sample['body'],
                    'reading_minutes' => max(1, (int) ceil(str_word_count(strip_tags($sample['body'])) / 200)),
                    'is_published' => true,
                    'published_at' => now()->subDays($index),
                    'created_by' => $teacher->id,
                ],
            );
        }
    }
}
