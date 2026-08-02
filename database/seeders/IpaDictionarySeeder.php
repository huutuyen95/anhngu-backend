<?php

namespace Database\Seeders;

use App\Models\IpaEntry;
use Illuminate\Database\Seeder;

/**
 * Seed từ điển phiên âm để tra IPA tự động. Nguồn: từ vựng phổ thông SGK lớp 6–9 (open data).
 * Số lượng seed hiện tại ~72 từ — đủ cho tra + auto-fill khi import; có thể bổ sung sau.
 */
class IpaDictionarySeeder extends Seeder
{
    public function run(): void
    {
        $meanings = $this->meanings();
        foreach ($this->words() as [$word, $ipa, $pos]) {
            $key = strtolower($word);
            IpaEntry::updateOrCreate(['word' => $key], [
                'ipa' => $ipa, 'pos' => $pos, 'meaning_vi' => $meanings[$key] ?? null,
            ]);
        }
    }

    /**
     * Nghĩa tiếng Việt cho từ điển tra cứu (FE 11).
     *
     * @return array<string, string>
     */
    private function meanings(): array
    {
        return [
            'apple' => 'quả táo', 'banana' => 'quả chuối', 'orange' => 'quả cam', 'water' => 'nước',
            'school' => 'trường học', 'teacher' => 'giáo viên', 'student' => 'học sinh', 'book' => 'quyển sách',
            'pencil' => 'bút chì', 'friend' => 'bạn bè', 'family' => 'gia đình', 'house' => 'ngôi nhà',
            'city' => 'thành phố', 'country' => 'đất nước', 'journey' => 'chuyến đi', 'souvenir' => 'quà lưu niệm',
            'weather' => 'thời tiết', 'holiday' => 'kỳ nghỉ', 'festival' => 'lễ hội', 'museum' => 'bảo tàng',
            'hospital' => 'bệnh viện', 'library' => 'thư viện', 'garden' => 'khu vườn', 'kitchen' => 'nhà bếp',
            'bridge' => 'cây cầu', 'mountain' => 'ngọn núi', 'river' => 'dòng sông', 'island' => 'hòn đảo',
            'beach' => 'bãi biển', 'forest' => 'khu rừng', 'animal' => 'động vật', 'elephant' => 'con voi',
            'dolphin' => 'cá heo', 'beautiful' => 'đẹp', 'happy' => 'vui vẻ', 'difficult' => 'khó',
            'important' => 'quan trọng', 'delicious' => 'ngon', 'famous' => 'nổi tiếng', 'expensive' => 'đắt',
            'comfortable' => 'thoải mái', 'modern' => 'hiện đại', 'traditional' => 'truyền thống',
            'friendly' => 'thân thiện', 'careful' => 'cẩn thận', 'travel' => 'du lịch', 'discover' => 'khám phá',
            'imagine' => 'tưởng tượng', 'remember' => 'ghi nhớ', 'celebrate' => 'ăn mừng', 'practise' => 'luyện tập',
            'develop' => 'phát triển', 'protect' => 'bảo vệ', 'improve' => 'cải thiện', 'communicate' => 'giao tiếp',
            'describe' => 'miêu tả', 'explain' => 'giải thích', 'listen' => 'lắng nghe', 'answer' => 'trả lời',
            'question' => 'câu hỏi', 'quickly' => 'một cách nhanh chóng', 'carefully' => 'một cách cẩn thận',
            'usually' => 'thường thường', 'together' => 'cùng nhau', 'already' => 'đã rồi', 'finally' => 'cuối cùng',
            'information' => 'thông tin', 'environment' => 'môi trường', 'technology' => 'công nghệ',
            'adventure' => 'cuộc phiêu lưu', 'knowledge' => 'kiến thức', 'weekend' => 'cuối tuần',
            // Dạng gốc cho lemmatize.
            'go' => 'đi', 'child' => 'đứa trẻ', 'good' => 'tốt', 'run' => 'chạy', 'make' => 'làm, chế tạo',
        ];
    }

    /**
     * @return array<int, array{0:string,1:string,2:string}>
     */
    private function words(): array
    {
        return [
            ['apple', '/ˈæp.əl/', 'n.'], ['banana', '/bəˈnɑː.nə/', 'n.'], ['orange', '/ˈɒr.ɪndʒ/', 'n.'],
            ['water', '/ˈwɔː.tər/', 'n.'], ['school', '/skuːl/', 'n.'], ['teacher', '/ˈtiː.tʃər/', 'n.'],
            ['student', '/ˈstjuː.dənt/', 'n.'], ['book', '/bʊk/', 'n.'], ['pencil', '/ˈpen.səl/', 'n.'],
            ['friend', '/frend/', 'n.'], ['family', '/ˈfæm.əl.i/', 'n.'], ['house', '/haʊs/', 'n.'],
            ['city', '/ˈsɪt.i/', 'n.'], ['country', '/ˈkʌn.tri/', 'n.'], ['journey', '/ˈdʒɜː.ni/', 'n.'],
            ['souvenir', '/ˌsuː.vənˈɪər/', 'n.'], ['weather', '/ˈweð.ər/', 'n.'], ['holiday', '/ˈhɒl.ə.deɪ/', 'n.'],
            ['festival', '/ˈfes.tɪ.vəl/', 'n.'], ['museum', '/mjuːˈziː.əm/', 'n.'], ['hospital', '/ˈhɒs.pɪ.təl/', 'n.'],
            ['library', '/ˈlaɪ.brər.i/', 'n.'], ['garden', '/ˈɡɑː.dən/', 'n.'], ['kitchen', '/ˈkɪtʃ.ɪn/', 'n.'],
            ['bridge', '/brɪdʒ/', 'n.'], ['mountain', '/ˈmaʊn.tɪn/', 'n.'], ['river', '/ˈrɪv.ər/', 'n.'],
            ['island', '/ˈaɪ.lənd/', 'n.'], ['beach', '/biːtʃ/', 'n.'], ['forest', '/ˈfɒr.ɪst/', 'n.'],
            ['animal', '/ˈæn.ɪ.məl/', 'n.'], ['elephant', '/ˈel.ɪ.fənt/', 'n.'], ['dolphin', '/ˈdɒl.fɪn/', 'n.'],
            ['beautiful', '/ˈbjuː.tɪ.fəl/', 'adj.'], ['happy', '/ˈhæp.i/', 'adj.'], ['difficult', '/ˈdɪf.ɪ.kəlt/', 'adj.'],
            ['important', '/ɪmˈpɔː.tənt/', 'adj.'], ['delicious', '/dɪˈlɪʃ.əs/', 'adj.'], ['famous', '/ˈfeɪ.məs/', 'adj.'],
            ['expensive', '/ɪkˈspen.sɪv/', 'adj.'], ['comfortable', '/ˈkʌm.fə.tə.bəl/', 'adj.'], ['modern', '/ˈmɒd.ən/', 'adj.'],
            ['traditional', '/trəˈdɪʃ.ən.əl/', 'adj.'], ['friendly', '/ˈfrend.li/', 'adj.'], ['careful', '/ˈkeə.fəl/', 'adj.'],
            ['travel', '/ˈtræv.əl/', 'v.'], ['discover', '/dɪˈskʌv.ər/', 'v.'], ['imagine', '/ɪˈmædʒ.ɪn/', 'v.'],
            ['remember', '/rɪˈmem.bər/', 'v.'], ['celebrate', '/ˈsel.ɪ.breɪt/', 'v.'], ['practise', '/ˈpræk.tɪs/', 'v.'],
            ['develop', '/dɪˈvel.əp/', 'v.'], ['protect', '/prəˈtekt/', 'v.'], ['improve', '/ɪmˈpruːv/', 'v.'],
            ['communicate', '/kəˈmjuː.nɪ.keɪt/', 'v.'], ['describe', '/dɪˈskraɪb/', 'v.'], ['explain', '/ɪkˈspleɪn/', 'v.'],
            ['listen', '/ˈlɪs.ən/', 'v.'], ['answer', '/ˈɑːn.sər/', 'v.'], ['question', '/ˈkwes.tʃən/', 'n.'],
            ['quickly', '/ˈkwɪk.li/', 'adv.'], ['carefully', '/ˈkeə.fəl.i/', 'adv.'], ['usually', '/ˈjuː.ʒu.ə.li/', 'adv.'],
            ['together', '/təˈɡeð.ər/', 'adv.'], ['already', '/ɔːlˈred.i/', 'adv.'], ['finally', '/ˈfaɪ.nəl.i/', 'adv.'],
            ['information', '/ˌɪn.fəˈmeɪ.ʃən/', 'n.'], ['environment', '/ɪnˈvaɪ.rən.mənt/', 'n.'], ['technology', '/tekˈnɒl.ə.dʒi/', 'n.'],
            ['adventure', '/ədˈven.tʃər/', 'n.'], ['knowledge', '/ˈnɒl.ɪdʒ/', 'n.'], ['weekend', '/ˌwiːkˈend/', 'n.'],
            ['go', '/ɡəʊ/', 'v.'], ['child', '/tʃaɪld/', 'n.'], ['good', '/ɡʊd/', 'adj.'],
            ['run', '/rʌn/', 'v.'], ['make', '/meɪk/', 'v.'],
        ];
    }
}
