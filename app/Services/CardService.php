<?php

namespace App\Services;

use App\Imports\CardsImport;
use App\Models\Card;
use App\Models\Deck;
use App\Repositories\CardRepository;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class CardService
{
    public function __construct(private readonly CardRepository $cards) {}

    public function create(Deck $deck, array $data): Card
    {
        return $this->cards->create($deck, $data);
    }

    public function update(Card $card, array $data): Card
    {
        return $this->cards->update($card, $data);
    }

    public function delete(Card $card): void
    {
        $this->cards->delete($card);
    }

    /**
     * Tra IPA + loại từ hàng loạt từ từ điển nội bộ.
     *
     * @param  array<int, string>  $words
     * @return array<string, array{ipa: string|null, pos: string|null}>
     */
    public function lookupIpa(array $words): array
    {
        $normalized = collect($words)->map(fn ($w) => strtolower(trim($w)))->filter()->unique();

        return $this->cards->ipaEntries($normalized->all());
    }

    /**
     * @param  array<int>  $orderedIds
     */
    public function reorder(Deck $deck, array $orderedIds): void
    {
        $this->cards->reorder($deck, $orderedIds);
    }

    public function uploadImage(Card $card, UploadedFile $file): Card
    {
        $path = $file->store('card-images', 'public');

        return $this->cards->updateMedia($card, 'image_url', asset('storage/'.$path));
    }

    public function uploadAudio(Card $card, UploadedFile $file): Card
    {
        $path = $file->store('card-audio', 'public');

        return $this->cards->updateMedia($card, 'audio_url', asset('storage/'.$path));
    }

    public function deleteAudio(Card $card): void
    {
        $this->cards->updateMedia($card, 'audio_url', null);
    }

    public function import(Deck $deck, UploadedFile $file, bool $dryRun, bool $autoIpa, bool $overwrite): array
    {
        $rows = Excel::toArray(new CardsImport, $file)[0] ?? [];
        if ($dryRun) {
            return $this->previewImport($deck, $rows);
        }

        return $this->commitImport($deck, $rows, $autoIpa, $overwrite);
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
            $this->cards->existingTerms($deck)
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

        return $this->cards->importRows($deck, $preview['rows'], $rows, $autoIpa, $overwrite);
    }
}
