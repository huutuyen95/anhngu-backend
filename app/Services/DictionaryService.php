<?php

namespace App\Services;

use App\Models\IpaEntry;

class DictionaryService
{
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
            $entry = IpaEntry::where('word', $cand)->first();
            if ($entry) {
                return [
                    'word' => $entry->word,
                    'ipa' => $entry->ipa,
                    'pos' => $entry->pos,
                    'meaning_vi' => $entry->meaning_vi,
                    'matched_from' => $cand !== $word ? $word : null,
                ];
            }
        }

        return null;
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
