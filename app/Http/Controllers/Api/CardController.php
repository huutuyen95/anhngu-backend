<?php

namespace App\Http\Controllers\Api;

use App\Exports\CardsImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Card\ImportCardsRequest;
use App\Http\Requests\Card\LookupIpaRequest;
use App\Http\Requests\Card\ReorderCardsRequest;
use App\Http\Requests\Card\StoreCardRequest;
use App\Http\Requests\Card\UpdateCardRequest;
use App\Http\Requests\Card\UploadCardAudioRequest;
use App\Http\Requests\Card\UploadCardImageRequest;
use App\Http\Resources\CardResource;
use App\Http\Responses\ApiResponse;
use App\Models\Card;
use App\Models\Deck;
use App\Services\CardService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CardController extends Controller
{
    public function __construct(private readonly CardService $cards) {}

    public function store(StoreCardRequest $request, Deck $deck): JsonResponse
    {
        return ApiResponse::resource(new CardResource($this->cards->create($deck, $request->validated())), 'card', 201);
    }

    public function update(UpdateCardRequest $request, Card $card): JsonResponse
    {
        return ApiResponse::resource(new CardResource($this->cards->update($card, $request->validated())), 'card');
    }

    public function destroy(Card $card): JsonResponse
    {
        $this->cards->delete($card);

        return ApiResponse::message('Đã xoá thẻ.');
    }

    public function reorder(ReorderCardsRequest $request, Deck $deck): JsonResponse
    {
        $this->cards->reorder($deck, $request->validated('ids'));

        return ApiResponse::message('Đã cập nhật thứ tự.');
    }

    public function uploadImage(UploadCardImageRequest $request, Card $card): JsonResponse
    {
        $card = $this->cards->uploadImage($card, $request->file('file'));

        return response()->json(['image_url' => $card->image_url]);
    }

    public function uploadAudio(UploadCardAudioRequest $request, Card $card): JsonResponse
    {
        $card = $this->cards->uploadAudio($card, $request->file('file'));

        return response()->json(['audio_url' => $card->audio_url]);
    }

    public function deleteAudio(Card $card): JsonResponse
    {
        $this->cards->deleteAudio($card);

        return ApiResponse::message('Đã xoá file audio, quay về đọc tự động.');
    }

    public function ipaLookup(LookupIpaRequest $request): JsonResponse
    {
        $words = array_filter(array_map('trim', explode(',', (string) ($request->validated('words') ?? ''))));

        return response()->json(['results' => $this->cards->lookupIpa($words)]);
    }

    public function import(ImportCardsRequest $request, Deck $deck): JsonResponse
    {
        return response()->json($this->cards->import(
            $deck, $request->file('file'), $request->boolean('dry_run'),
            $request->boolean('auto_ipa', true), $request->boolean('overwrite'),
        ));
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new CardsImportTemplateExport, 'mau-import-tu-vung.xlsx');
    }
}
