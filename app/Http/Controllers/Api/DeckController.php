<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deck\ListCardsRequest;
use App\Http\Requests\Deck\ListDecksRequest;
use App\Http\Requests\Deck\PublishDeckRequest;
use App\Http\Requests\Deck\StoreDeckRequest;
use App\Http\Requests\Deck\UpdateDeckRequest;
use App\Http\Resources\CardResource;
use App\Http\Resources\DeckResource;
use App\Http\Responses\ApiResponse;
use App\Models\Deck;
use App\Services\DeckService;
use Illuminate\Http\JsonResponse;

class DeckController extends Controller
{
    public function __construct(private readonly DeckService $decks) {}

    public function index(ListDecksRequest $request): JsonResponse
    {
        $page = $this->decks->paginate($request->validated());

        return ApiResponse::paginated(DeckResource::collection($page->items()), $page);
    }

    public function store(StoreDeckRequest $request): JsonResponse
    {
        $deck = $this->decks->detail($this->decks->create($request->validated(), $request->user()));

        return ApiResponse::resource(new DeckResource($deck), 'deck', 201);
    }

    public function show(Deck $deck): JsonResponse
    {
        return ApiResponse::resource(new DeckResource($this->decks->detail($deck)), 'deck');
    }

    public function update(UpdateDeckRequest $request, Deck $deck): JsonResponse
    {
        return ApiResponse::resource(new DeckResource($this->decks->detail($this->decks->update($deck, $request->validated()))), 'deck');
    }

    public function publish(PublishDeckRequest $request, Deck $deck): JsonResponse
    {
        $deck = $this->decks->publish($deck, $request->boolean('is_published'));

        return response()->json(['is_published' => $deck->is_published]);
    }

    public function destroy(Deck $deck): JsonResponse
    {
        $sessions = $this->decks->sessionsUsing($deck);
        if ($sessions !== []) {
            return response()->json([
                'code' => 'deck_in_use',
                'message' => 'Bộ từ đang được giao trong một số buổi — chỉ có thể Ẩn, không xoá được.',
                'sessions' => $sessions,
            ], 409);
        }
        $this->decks->delete($deck);

        return ApiResponse::message('Đã xoá bộ từ.');
    }

    public function duplicate(Deck $deck): JsonResponse
    {
        return ApiResponse::resource(new DeckResource($this->decks->detail($this->decks->duplicate($deck))), 'deck', 201);
    }

    public function cards(ListCardsRequest $request, Deck $deck): JsonResponse
    {
        $page = $this->decks->cards($deck, $request->validated());

        return ApiResponse::paginated(CardResource::collection($page->items()), $page);
    }
}
