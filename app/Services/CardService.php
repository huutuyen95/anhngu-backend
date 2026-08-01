<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Deck;
use App\Models\IpaEntry;
use Illuminate\Support\Facades\DB;

class CardService
{
    /**
     * Tra IPA + loại từ hàng loạt từ từ điển nội bộ.
     *
     * @param  array<int, string>  $words
     * @return array<string, array{ipa: string|null, pos: string|null}>
     */
    public function lookupIpa(array $words): array
    {
        $normalized = collect($words)->map(fn ($w) => strtolower(trim($w)))->filter()->unique();

        return IpaEntry::whereIn('word', $normalized)
            ->get()
            ->keyBy('word')
            ->map(fn (IpaEntry $e) => ['ipa' => $e->ipa, 'pos' => $e->pos])
            ->all();
    }

    /**
     * @param  array<int>  $orderedIds
     */
    public function reorder(Deck $deck, array $orderedIds): void
    {
        if ($orderedIds === []) {
            return;
        }

        $cases = [];
        $ids = [];
        foreach ($orderedIds as $index => $id) {
            $id = (int) $id;
            $cases[] = "WHEN {$id} THEN ".($index + 1);
            $ids[] = $id;
        }

        Card::where('deck_id', $deck->id)
            ->whereIn('id', $ids)
            ->update(['order' => DB::raw('CASE id '.implode(' ', $cases).' END')]);
    }

    /**
     * Xem trước import (KHÔNG ghi DB).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function previewImport(Deck $deck, array $rows): array
    {
        $existing = array_flip(
            $deck->cards()->pluck('term')->map(fn ($t) => strtolower($t))->all()
        );
        $dict = $this->lookupIpa(array_map(fn ($r) => (string) ($r['term'] ?? ''), $rows));

        $result = [];
        $summary = ['ok' => 0, 'need_ipa' => 0, 'duplicate' => 0, 'error' => 0];
        $seen = [];

        foreach ($rows as $i => $row) {
            $term = trim((string) ($row['term'] ?? ''));
            $meaning = trim((string) ($row['meaning'] ?? ''));
            $ipa = trim((string) ($row['ipa'] ?? ''));
            $reasons = [];
            $status = 'ok';

            if ($term === '') {
                $reasons[] = 'Thiếu từ';
            }
            if ($meaning === '') {
                $reasons[] = 'Thiếu nghĩa';
            }

            $key = strtolower($term);
            if ($reasons) {
                $status = 'error';
            } elseif (isset($existing[$key]) || isset($seen[$key])) {
                $status = 'duplicate';
                $reasons[] = 'Từ đã có trong bộ';
            } elseif ($ipa === '' && isset($dict[$key])) {
                $status = 'need_ipa';
                $reasons[] = 'Sẽ tự điền phiên âm khi import';
            }

            $seen[$key] = true;
            $summary[$status]++;
            $result[] = [
                'row' => $i + 1,
                'term' => $term,
                'meaning' => $meaning,
                'ipa' => $ipa ?: ($dict[$key]['ipa'] ?? null),
                'pos' => trim((string) ($row['pos'] ?? '')) ?: ($dict[$key]['pos'] ?? null),
                'status' => $status,
                'reasons' => $reasons,
            ];
        }

        return ['rows' => $result, 'summary' => $summary];
    }

    /**
     * Ghi thật các thẻ hợp lệ. autoIpa = tự điền IPA thiếu từ từ điển. overwrite = ghi đè trùng.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int, error: int}
     */
    public function commitImport(Deck $deck, array $rows, bool $autoIpa, bool $overwrite): array
    {
        $preview = $this->previewImport($deck, $rows);
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 0];

        DB::transaction(function () use ($deck, $preview, $rows, $autoIpa, $overwrite, &$counts) {
            $order = (int) ($deck->cards()->max('order') ?? 0);

            foreach ($preview['rows'] as $idx => $line) {
                if ($line['status'] === 'error') {
                    $counts['error']++;

                    continue;
                }

                // IPA/POS trong preview có thể đã gắn gợi ý từ điển — chỉ dùng khi autoIpa.
                $rawIpa = trim((string) ($rows[$idx]['ipa'] ?? ''));
                $rawPos = trim((string) ($rows[$idx]['pos'] ?? ''));
                $ipa = $rawIpa !== '' ? $rawIpa : ($autoIpa ? $line['ipa'] : null);
                $pos = $rawPos !== '' ? $rawPos : ($autoIpa ? $line['pos'] : null);

                if ($line['status'] === 'duplicate') {
                    if (! $overwrite) {
                        $counts['skipped']++;

                        continue;
                    }
                    $deck->cards()->where('term', $line['term'])->update([
                        'meaning' => $line['meaning'],
                        'ipa' => $ipa,
                        'pos' => $pos,
                        'example' => $rows[$idx]['example'] ?? null,
                    ]);
                    $counts['updated']++;

                    continue;
                }

                $deck->cards()->create([
                    'order' => ++$order,
                    'term' => $line['term'],
                    'meaning' => $line['meaning'],
                    'ipa' => $ipa,
                    'pos' => $pos,
                    'example' => $rows[$idx]['example'] ?? null,
                ]);
                $counts['created']++;
            }
        });

        return $counts;
    }
}
