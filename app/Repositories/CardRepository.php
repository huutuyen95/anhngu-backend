<?php

namespace App\Repositories;

use App\Models\Card;
use App\Models\Deck;
use App\Models\IpaEntry;
use Illuminate\Support\Facades\DB;

class CardRepository
{
    public function create(Deck $deck, array $data): Card
    {
        $data['order'] = (int) ($deck->cards()->max('order') ?? 0) + 1;

        return $deck->cards()->create($data);
    }

    public function update(Card $card, array $data): Card
    {
        $card->update($data);

        return $card;
    }

    public function delete(Card $card): void
    {
        $card->delete();
    }

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
        Card::where('deck_id', $deck->id)->whereIn('id', $ids)
            ->update(['order' => DB::raw('CASE id '.implode(' ', $cases).' END')]);
    }

    public function updateMedia(Card $card, string $column, ?string $url): Card
    {
        $card->update([$column => $url]);

        return $card;
    }

    public function ipaEntries(array $words): array
    {
        return IpaEntry::whereIn('word', $words)->get()->keyBy('word')
            ->map(fn (IpaEntry $entry) => ['ipa' => $entry->ipa, 'pos' => $entry->pos])->all();
    }

    public function existingTerms(Deck $deck): array
    {
        return $deck->cards()->pluck('term')->map(fn ($term) => strtolower($term))->all();
    }

    public function importRows(Deck $deck, array $previewRows, array $sourceRows, bool $autoIpa, bool $overwrite): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 0];
        DB::transaction(function () use ($deck, $previewRows, $sourceRows, $autoIpa, $overwrite, &$counts) {
            $order = (int) ($deck->cards()->max('order') ?? 0);
            foreach ($previewRows as $index => $line) {
                if ($line['status'] === 'error') {
                    $counts['error']++;

                    continue;
                }
                $rawIpa = trim((string) ($sourceRows[$index]['ipa'] ?? ''));
                $rawPos = trim((string) ($sourceRows[$index]['pos'] ?? ''));
                $ipa = $rawIpa !== '' ? $rawIpa : ($autoIpa ? $line['ipa'] : null);
                $pos = $rawPos !== '' ? $rawPos : ($autoIpa ? $line['pos'] : null);
                if ($line['status'] === 'duplicate') {
                    if (! $overwrite) {
                        $counts['skipped']++;

                        continue;
                    }
                    $deck->cards()->where('term', $line['term'])->update([
                        'meaning' => $line['meaning'], 'ipa' => $ipa, 'pos' => $pos,
                        'example' => $sourceRows[$index]['example'] ?? null,
                    ]);
                    $counts['updated']++;

                    continue;
                }
                $deck->cards()->create([
                    'order' => ++$order, 'term' => $line['term'], 'meaning' => $line['meaning'],
                    'ipa' => $ipa, 'pos' => $pos, 'example' => $sourceRows[$index]['example'] ?? null,
                ]);
                $counts['created']++;
            }
        });

        return $counts;
    }
}
