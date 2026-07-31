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
        DB::transaction(function () use ($deck, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                Card::where('deck_id', $deck->id)->where('id', $id)->update(['order' => $index + 1]);
            }
        });
    }

    /**
     * Xem trước import (KHÔNG ghi DB).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function previewImport(Deck $deck, array $rows): array
    {
        $existing = $deck->cards()->pluck('term')->map(fn ($t) => strtolower($t))->all();
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
            } elseif (in_array($key, $existing, true) || in_array($key, $seen, true)) {
                $status = 'duplicate';
                $reasons[] = 'Từ đã có trong bộ';
            } elseif ($ipa === '' && isset($dict[$key])) {
                $status = 'need_ipa';
                $reasons[] = 'Sẽ tự điền phiên âm khi import';
            }

            $seen[] = $key;
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
        $dict = $this->lookupIpa(array_map(fn ($r) => (string) ($r['term'] ?? ''), $rows));
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 0];

        DB::transaction(function () use ($deck, $preview, $rows, $autoIpa, $overwrite, $dict, &$counts) {
            $order = (int) ($deck->cards()->max('order') ?? 0);

            foreach ($preview['rows'] as $idx => $line) {
                if ($line['status'] === 'error') {
                    $counts['error']++;

                    continue;
                }
                $key = strtolower($line['term']);
                $ipa = $line['ipa'];
                if (! $ipa && $autoIpa && isset($dict[$key])) {
                    $ipa = $dict[$key]['ipa'];
                }

                if ($line['status'] === 'duplicate') {
                    if (! $overwrite) {
                        $counts['skipped']++;

                        continue;
                    }
                    $deck->cards()->where('term', $line['term'])->update([
                        'meaning' => $line['meaning'],
                        'ipa' => $ipa,
                        'pos' => $line['pos'],
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
                    'pos' => $line['pos'],
                    'example' => $rows[$idx]['example'] ?? null,
                ]);
                $counts['created']++;
            }
        });

        return $counts;
    }
}
