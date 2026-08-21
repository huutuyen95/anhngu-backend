<?php

namespace App\Services;

use App\Models\UserVocab;
use App\Repositories\DictionaryRepository;

class DictionaryService
{
    public function __construct(private readonly DictionaryRepository $dictionary) {}

    /** Động từ / danh từ bất quy tắc phổ biến → dạng gốc. */
    private const IRREGULAR = [
        'went' => 'go', 'gone' => 'go', 'goes' => 'go',
        'children' => 'child', 'men' => 'man', 'women' => 'woman', 'people' => 'person',
        'feet' => 'foot', 'teeth' => 'tooth', 'mice' => 'mouse',
        'was' => 'be', 'were' => 'be', 'been' => 'be', 'is' => 'be', 'are' => 'be', 'am' => 'be',
        'had' => 'have', 'has' => 'have', 'did' => 'do', 'does' => 'do', 'done' => 'do',
        'said' => 'say', 'saw' => 'see', 'seen' => 'see', 'made' => 'make', 'came' => 'come',
        'took' => 'take', 'taken' => 'take', 'got' => 'get', 'gotten' => 'get',
        'ran' => 'run', 'ate' => 'eat', 'eaten' => 'eat', 'wrote' => 'write', 'written' => 'write',
        'bought' => 'buy', 'thought' => 'think', 'brought' => 'bring', 'taught' => 'teach',
        'found' => 'find', 'told' => 'tell', 'left' => 'leave', 'felt' => 'feel', 'kept' => 'keep',
        'better' => 'good', 'best' => 'good', 'worse' => 'bad', 'worst' => 'bad',
    ];

    /**
     * Tra 1 từ, tự đưa về dạng gốc nếu là dạng chia. Trả null nếu không có.
     *
     * @return array<string, mixed>|null
     */
    public function lookup(string $raw): ?array
    {
        $word = strtolower(trim($raw));
        if ($word === '') {
            return null;
        }

        foreach ($this->candidates($word) as $cand) {
            $entry = $this->dictionary->findIpa($cand);
            if ($entry) {
                return [
                    'word' => $entry->word,
                    'ipa' => $this->normalizeIpa($entry->ipa),
                    'pos' => $entry->pos,
                    'meaning_vi' => $entry->meaning_vi,
                    'matched_from' => $cand !== $word ? $word : null,
                ];
            }
        }

        return null;
    }

    /**
     * Trả phiên âm TRẦN, không dấu `/…/`.
     *
     * Dữ liệu đến từ hai nguồn ghi khác nhau — bộ soạn tay lưu kèm dấu (`/ˈæp.əl/`), bộ
     * nạp từ Wiktionary lưu trần (`ˈθɪŋk`). FE luôn tự bọc `/…/` khi hiện, nên nếu không
     * gọt ở đây thì từ của bộ cũ hiện thành `//ˈæp.əl//`.
     */
    private function normalizeIpa(?string $ipa): ?string
    {
        $ipa = trim((string) $ipa, "/[] \t");

        return $ipa === '' ? null : $ipa;
    }

    public function saveVocab(int $userId, array $data): UserVocab
    {
        return $this->dictionary->saveVocab($userId, $data);
    }

    /**
     * Sinh các dạng gốc có thể của 1 từ (bất quy tắc + bỏ hậu tố đơn giản).
     *
     * @return array<int, string>
     */
    private function candidates(string $word): array
    {
        $out = [$word];
        if (isset(self::IRREGULAR[$word])) {
            $out[] = self::IRREGULAR[$word];
        }
        // -ies → -y (studies → study)
        if (str_ends_with($word, 'ies') && strlen($word) > 4) {
            $out[] = substr($word, 0, -3).'y';
        }
        // -es (boxes → box), -s (cats → cat)
        if (str_ends_with($word, 'es') && strlen($word) > 3) {
            $out[] = substr($word, 0, -2);
        }
        if (str_ends_with($word, 's') && strlen($word) > 2) {
            $out[] = substr($word, 0, -1);
        }
        // -ing (running → run / running → runn → skip double)
        if (str_ends_with($word, 'ing') && strlen($word) > 5) {
            $base = substr($word, 0, -3);
            $out[] = $base;
            $out[] = $base.'e'; // making → make
        }
        // -ed (played → play, -ied → y)
        if (str_ends_with($word, 'ied') && strlen($word) > 4) {
            $out[] = substr($word, 0, -3).'y';
        }
        if (str_ends_with($word, 'ed') && strlen($word) > 3) {
            $out[] = substr($word, 0, -2);
            $out[] = substr($word, 0, -1); // used → use
        }

        return array_values(array_unique($out));
    }
}
