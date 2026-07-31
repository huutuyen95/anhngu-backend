<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Http\Resources\DeckResource;
use App\Models\Deck;
use App\Services\DeckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeckController extends Controller
{
    public function __construct(private readonly DeckService $decks) {}

    public function index(Request $request): JsonResponse
    {
        $audioReady = fn ($q) => $q->where(fn ($w) => $w->whereNotNull('audio_url')->orWhereNotNull('ipa'));

        $page = Deck::query()
            ->with(['owner:id,name', 'classrooms:id,name'])
            ->withCount(['cards', 'cards as audio_ready_count' => $audioReady])
            ->when($request->input('q'), fn ($q, $t) => $q->where('name', 'like', "%{$t}%"))
            ->when($request->input('classroom_id'), fn ($q, $id) => $q->whereHas('classrooms', fn ($c) => $c->where('classrooms.id', $id)))
            ->when($request->has('is_published') && $request->input('is_published') !== '', fn ($q) => $q->where('is_published', $request->boolean('is_published')))
            ->latest()
            ->paginate((int) $request->input('per_page', 24));

        return response()->json([
            'data' => DeckResource::collection($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateDeck($request);
        $deck = $this->decks->create($data, $request->user());

        return response()->json(['deck' => new DeckResource($deck->load(['owner:id,name', 'classrooms:id,name'])->loadCount('cards'))], 201);
    }

    public function show(Deck $deck): JsonResponse
    {
        $deck->load(['owner:id,name', 'classrooms:id,name'])
            ->loadCount(['cards', 'cards as audio_ready_count' => fn ($q) => $q->where(fn ($w) => $w->whereNotNull('audio_url')->orWhereNotNull('ipa'))]);

        return response()->json(['deck' => new DeckResource($deck)]);
    }

    public function update(Request $request, Deck $deck): JsonResponse
    {
        $data = $this->validateDeck($request, updating: true);
        $updated = $this->decks->update($deck, $data);

        return response()->json(['deck' => new DeckResource($updated->load(['owner:id,name', 'classrooms:id,name'])->loadCount('cards'))]);
    }

    public function publish(Request $request, Deck $deck): JsonResponse
    {
        $data = $request->validate(['is_published' => ['required', 'boolean']]);
        $deck->update(['is_published' => $data['is_published']]);

        return response()->json(['is_published' => $deck->is_published]);
    }

    public function destroy(Deck $deck): JsonResponse
    {
        $sessions = $this->decks->sessionsUsing($deck);
        if ($sessions) {
            return response()->json([
                'code' => 'deck_in_use',
                'message' => 'Bộ từ đang được giao trong một số buổi — chỉ có thể Ẩn, không xoá được.',
                'sessions' => $sessions,
            ], 409);
        }

        $deck->delete();

        return response()->json(['message' => 'Đã xoá bộ từ.']);
    }

    public function duplicate(Deck $deck): JsonResponse
    {
        $copy = $this->decks->duplicate($deck);

        return response()->json(['deck' => new DeckResource($copy->load('classrooms:id,name')->loadCount('cards'))], 201);
    }

    public function cards(Request $request, Deck $deck): JsonResponse
    {
        $cards = $deck->cards()
            ->when($request->input('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('term', 'like', "%{$t}%")->orWhere('meaning', 'like', "%{$t}%")))
            ->when($request->input('missing') === 'audio', fn ($q) => $q->whereNull('audio_url')->whereNull('ipa'))
            ->when($request->input('missing') === 'image', fn ($q) => $q->whereNull('image_url'))
            ->when($request->input('missing') === 'ipa', fn ($q) => $q->whereNull('ipa'))
            ->orderBy('order')
            ->get();

        return response()->json(['data' => CardResource::collection($cards)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDeck(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'classroom_ids' => ['sometimes', 'array'],
            'classroom_ids.*' => ['integer', 'exists:classrooms,id'],
            'description' => ['nullable', 'string'],
            'tts_voice' => ['sometimes', Rule::in(['en-GB-female', 'en-GB-male', 'en-US-female', 'en-US-male'])],
            'tts_rate' => ['sometimes', 'numeric', 'between:0.5,1.5'],
            'tts_repeat' => ['sometimes', Rule::in(['1', '2', 'auto'])],
            'is_published' => ['sometimes', 'boolean'],
        ]);
    }
}
