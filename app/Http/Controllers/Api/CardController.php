<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Imports\CardsImport;
use App\Models\Card;
use App\Models\Deck;
use App\Services\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CardController extends Controller
{
    public function __construct(private readonly CardService $cards) {}

    public function store(Request $request, Deck $deck): JsonResponse
    {
        $data = $this->validateCard($request);
        $data['order'] = (int) ($deck->cards()->max('order') ?? 0) + 1;
        $card = $deck->cards()->create($data);

        return response()->json(['card' => new CardResource($card)], 201);
    }

    public function update(Request $request, Card $card): JsonResponse
    {
        $card->update($this->validateCard($request, updating: true));

        return response()->json(['card' => new CardResource($card)]);
    }

    public function destroy(Card $card): JsonResponse
    {
        $card->delete();

        return response()->json(['message' => 'Đã xoá thẻ.']);
    }

    public function reorder(Request $request, Deck $deck): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);
        $this->cards->reorder($deck, $data['ids']);

        return response()->json(['message' => 'Đã cập nhật thứ tự.']);
    }

    public function uploadImage(Request $request, Card $card): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $path = $request->file('file')->store('card-images', 'public');
        $card->update(['image_url' => asset('storage/'.$path)]);

        return response()->json(['image_url' => $card->image_url]);
    }

    public function uploadAudio(Request $request, Card $card): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:mp3,mpga,wav,m4a', 'max:2048']]);
        $path = $request->file('file')->store('card-audio', 'public');
        $card->update(['audio_url' => asset('storage/'.$path)]);

        return response()->json(['audio_url' => $card->audio_url]);
    }

    public function deleteAudio(Card $card): JsonResponse
    {
        $card->update(['audio_url' => null]);

        return response()->json(['message' => 'Đã xoá file audio, quay về đọc tự động.']);
    }

    public function ipaLookup(Request $request): JsonResponse
    {
        $words = array_filter(array_map('trim', explode(',', (string) $request->input('words'))));

        return response()->json(['results' => $this->cards->lookupIpa($words)]);
    }

    public function import(Request $request, Deck $deck): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']]);
        $rows = Excel::toArray(new CardsImport, $request->file('file'))[0] ?? [];

        if ($request->boolean('dry_run')) {
            return response()->json($this->cards->previewImport($deck, $rows));
        }

        return response()->json($this->cards->commitImport(
            $deck,
            $rows,
            $request->boolean('auto_ipa', true),
            $request->boolean('overwrite', false),
        ));
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new \App\Exports\CardsImportTemplateExport, 'mau-import-tu-vung.xlsx');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCard(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'term' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'meaning' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'pos' => ['nullable', 'string', 'max:12'],
            'ipa' => ['nullable', 'string', 'max:80'],
            'example' => ['nullable', 'string'],
        ]);
    }
}
