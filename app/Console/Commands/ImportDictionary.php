<?php

namespace App\Console\Commands;

use App\Repositories\DictionaryRepository;
use Illuminate\Console\Command;

/**
 * Nạp từ điển Anh–Việt vào bảng `ipa_dictionary` (dùng cho tra từ khi bôi đen).
 *
 * Nguồn: bản trích máy đọc được của Wiktionary tiếng Việt (kaikki.org / Wiktextract),
 * giấy phép CC BY-SA + GFDL — dùng được cho mục đích thương mại, chỉ cần ghi công.
 * Tải tại: https://kaikki.org/dictionary/downloads/vi/vi-extract.jsonl
 *
 *   php artisan dictionary:import /đường/dẫn/vi-extract.jsonl
 *
 * File là JSON Lines, mỗi dòng một mục từ. Ta chỉ lấy mục `lang_code = "en"` (từ tiếng Anh
 * được giải nghĩa bằng tiếng Việt) — đúng thứ học viên cần khi bôi đen từ trong đề.
 */
class ImportDictionary extends Command
{
    protected $signature = 'dictionary:import
        {file : Đường dẫn file .jsonl}
        {--chunk=2000 : Số dòng ghi mỗi lượt}
        {--overwrite : Ghi đè cả những từ đã có (mặc định chỉ bù chỗ thiếu)}';

    protected $description = 'Nạp từ điển Anh–Việt từ bản trích Wiktionary (JSONL)';

    /** Từ đơn, chỉ chữ cái — bỏ cụm từ, tên riêng, ký tự lạ. */
    private const WORD_PATTERN = "/^[a-z][a-z'-]{0,58}$/";

    /**
     * Ký hiệu của từ điển American Heritage (ă ĭ ō ʹ …) lẫn trong dữ liệu Wiktionary.
     * Đây KHÔNG phải IPA — hiện cho học viên sẽ dạy sai cách đọc, nên loại thẳng.
     */
    private const NOT_IPA = '/[ʹ˝\x{0306}\x{0304}\x{035E}ăĕĭŏŭāēīōūȯ]/u';

    /** Ít nhất một ký tự đặc trưng IPA thì mới coi là phiên âm thật. */
    private const LOOKS_IPA = '/[ˈˌːɪʊəɛɔæŋʃʒθðʌɑɜɒʁçɹɾɐø]/u';

    /** Nhãn loại từ cho gọn, khớp cách hiển thị sẵn có ("n." · "v." · "adj."). */
    private const POS = [
        'noun' => 'n.', 'name' => 'n.', 'verb' => 'v.', 'adj' => 'adj.',
        'adv' => 'adv.', 'pron' => 'pron.', 'prep' => 'prep.', 'conj' => 'conj.',
        'det' => 'det.', 'num' => 'num.', 'intj' => 'intj.', 'phrase' => 'phr.',
    ];

    public function handle(DictionaryRepository $dictionary): int
    {
        $path = (string) $this->argument('file');

        if (! is_readable($path)) {
            $this->error("Không đọc được file: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $chunkSize = max(100, (int) $this->option('chunk'));
        $overwrite = (bool) $this->option('overwrite');

        $rows = [];
        $seen = [];
        $read = 0;
        $kept = 0;
        $withIpa = 0;

        $this->info('Đang đọc '.basename($path).'…');
        $bar = $this->output->createProgressBar();
        $bar->start();

        while (($line = fgets($handle)) !== false) {
            $read++;

            if ($read % 20000 === 0) {
                $bar->advance(20000);
            }

            $entry = $this->parse($line);

            if (! $entry) {
                continue;
            }

            [$word, $ipa, $pos, $meaning] = $entry;

            // Một từ có nhiều mục (mỗi loại từ một mục). Giữ mục ĐẦU tiên — Wiktionary xếp
            // nghĩa phổ thông lên trước — nhưng vẫn bù IPA nếu mục sau mới có.
            if (isset($seen[$word])) {
                // Bù phiên âm cho mục đã gặp — chỉ khi nó CÒN trong lô chưa ghi. Đã ghi
                // xuống DB rồi thì thôi, lần nạp sau sẽ bù (COALESCE giữ chỗ trống).
                if ($ipa && ! $seen[$word] && isset($rows[$word])) {
                    $rows[$word]['ipa'] = $ipa;
                    $seen[$word] = true;
                    $withIpa++;
                }

                continue;
            }

            $seen[$word] = (bool) $ipa;
            $rows[$word] = ['word' => $word, 'ipa' => $ipa, 'pos' => $pos, 'meaning_vi' => $meaning];
            $kept++;

            if ($ipa) {
                $withIpa++;
            }

            if (count($rows) >= $chunkSize) {
                $dictionary->upsertMany(array_values($rows), $overwrite);
                $rows = [];
            }
        }

        if ($rows !== []) {
            $dictionary->upsertMany(array_values($rows), $overwrite);
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        $this->info('Đã đọc     : '.number_format($read).' dòng');
        $this->info('Giữ lại    : '.number_format($kept).' từ tiếng Anh');
        $this->info('Có phiên âm: '.number_format($withIpa));
        $this->info('Tổng trong DB: '.number_format($dictionary->countEntries()).' từ');

        return self::SUCCESS;
    }

    /**
     * Một dòng JSONL → [word, ipa, pos, meaning_vi], hoặc null nếu không dùng được.
     *
     * @return array{0: string, 1: ?string, 2: ?string, 3: string}|null
     */
    private function parse(string $line): ?array
    {
        $data = json_decode(trim($line), true);

        if (! is_array($data) || ($data['lang_code'] ?? null) !== 'en') {
            return null;
        }

        $word = mb_strtolower(trim((string) ($data['word'] ?? '')));

        if (! preg_match(self::WORD_PATTERN, $word)) {
            return null;
        }

        $meaning = $this->meaning($data['senses'] ?? []);

        if ($meaning === '') {
            return null;   // không có nghĩa thì nạp vào cũng vô ích
        }

        return [$word, $this->ipa($data['sounds'] ?? []), $this->pos($data['pos'] ?? null), $meaning];
    }

    /** Gộp vài nghĩa đầu thành một dòng, cắt vừa cột `meaning_vi` (varchar 255). */
    private function meaning(array $senses): string
    {
        $glosses = [];

        foreach ($senses as $sense) {
            foreach ($sense['glosses'] ?? [] as $gloss) {
                $gloss = trim((string) $gloss);

                if ($gloss !== '' && ! in_array($gloss, $glosses, true)) {
                    $glosses[] = $gloss;
                }
            }

            if (count($glosses) >= 3) {
                break;
            }
        }

        return mb_substr(implode('; ', array_slice($glosses, 0, 3)), 0, 255);
    }

    /**
     * Phiên âm IPA đầu tiên dùng được.
     *
     * Dữ liệu Wiktionary lẫn ký hiệu American Heritage (`byo͞oʹtĭ-fəl`) — trông giống phiên
     * âm nhưng đọc theo là sai. Chỉ nhận chuỗi có ký tự IPA thật và không có ký hiệu AHD.
     */
    private function ipa(array $sounds): ?string
    {
        foreach ($sounds as $sound) {
            $ipa = trim((string) ($sound['ipa'] ?? ''));

            if ($ipa === '') {
                continue;
            }

            // FE tự bọc dấu /…/ khi hiển thị nên lưu trần.
            $ipa = trim($ipa, "/[] \t");

            if ($ipa === '' || mb_strlen($ipa) > 110) {
                continue;
            }

            if (preg_match(self::NOT_IPA, $ipa) || ! preg_match(self::LOOKS_IPA, $ipa)) {
                continue;
            }

            return $ipa;
        }

        return null;
    }

    private function pos(?string $pos): ?string
    {
        return self::POS[$pos] ?? null;
    }
}
