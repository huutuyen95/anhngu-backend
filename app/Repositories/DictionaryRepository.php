<?php

namespace App\Repositories;

use App\Models\IpaEntry;
use App\Models\UserVocab;
use Illuminate\Support\Facades\DB;

class DictionaryRepository
{
    public function findIpa(string $word): ?IpaEntry
    {
        return IpaEntry::where('word', $word)->first();
    }

    /**
     * Nạp hàng loạt mục từ.
     *
     * `$overwrite = false` (mặc định): chỉ THÊM từ mới và bù chỗ còn trống, KHÔNG đè lên
     * dữ liệu đã có — 77 mục seed ban đầu là từ SGK đã soát tay nên quý hơn dữ liệu máy trích.
     * Phải viết SQL tay vì `upsert()` của Laravel không nhận biểu thức (cần COALESCE).
     *
     * @param  array<int, array{word: string, ipa: ?string, pos: ?string, meaning_vi: string}>  $rows
     */
    public function upsertMany(array $rows, bool $overwrite = false): void
    {
        if ($rows === []) {
            return;
        }

        if ($overwrite) {
            IpaEntry::upsert($rows, ['word'], ['ipa', 'pos', 'meaning_vi']);

            return;
        }

        $bindings = [];

        foreach ($rows as $row) {
            array_push($bindings, $row['word'], $row['ipa'], $row['pos'], $row['meaning_vi']);
        }

        $values = implode(', ', array_fill(0, count($rows), '(?, ?, ?, ?)'));

        // `AS new`: cú pháp thay cho VALUES() đã bị MySQL 8 đánh dấu lỗi thời.
        DB::statement(
            "INSERT INTO ipa_dictionary (word, ipa, pos, meaning_vi) VALUES {$values} AS new
             ON DUPLICATE KEY UPDATE
                 ipa = COALESCE(ipa_dictionary.ipa, new.ipa),
                 pos = COALESCE(ipa_dictionary.pos, new.pos),
                 meaning_vi = COALESCE(ipa_dictionary.meaning_vi, new.meaning_vi)",
            $bindings,
        );
    }

    public function countEntries(): int
    {
        return IpaEntry::count();
    }

    public function saveVocab(int $userId, array $data): UserVocab
    {
        return UserVocab::updateOrCreate(
            ['user_id' => $userId, 'word' => strtolower(trim($data['word']))],
            ['meaning' => $data['meaning'] ?? null, 'ipa' => $data['ipa'] ?? null, 'created_at' => now()],
        );
    }
}
