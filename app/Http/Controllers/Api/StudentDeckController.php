<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deck\CompleteDeckSessionRequest;
use App\Http\Requests\Deck\StudyDeckRequest;
use App\Http\Requests\Deck\UpdateCardProgressRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\Classroom;
use App\Models\Deck;
use App\Services\StudentDeckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDeckController extends Controller
{
    public function __construct(private readonly StudentDeckService $decks) {}

    public function library(Request $request): JsonResponse
    {
        $decks = $this->decks->library($request->user());

        return response()->json(['data' => $decks->map(fn (Deck $d) => ['id' => $d->id, 'name' => $d->name, 'cards_count' => $d->cards_count, 'learned_count' => $d->learned_count, 'category' => $d->category ? ['id' => $d->category->id, 'name' => $d->category->name, 'order' => $d->category->order] : null, 'classrooms' => $d->classrooms->map(fn (Classroom $c) => ['id' => $c->id, 'name' => $c->name])->values()])]);
    }

    public function study(StudyDeckRequest $request, Deck $deck): JsonResponse
    {
        $classId = $this->decks->classroomId($request->user(), $request->integer('classroom_id') ?: null);
        $result = $this->decks->study($request->user(), $deck, $classId);

        return response()->json(['deck' => ['id' => $deck->id, 'name' => $deck->name, 'tts_voice' => $deck->tts_voice, 'tts_rate' => (float) $deck->tts_rate, 'tts_repeat' => $deck->tts_repeat], 'progress' => ['known' => $result['known'], 'total' => $result['cards']->count()], 'cards' => CardResource::collection($result['cards'])]);
    }

    public function progress(UpdateCardProgressRequest $request, Card $card): JsonResponse
    {
        $id = $this->decks->classroomId($request->user(), $request->integer('classroom_id') ?: null);

        return response()->json(['status' => $this->decks->progress($request->user(), $card, $id, $request->validated('status'))]);
    }

    public function sessionComplete(CompleteDeckSessionRequest $request, Deck $deck): JsonResponse
    {
        $id = $this->decks->classroomId($request->user(), $request->integer('classroom_id') ?: null);

        return response()->json($this->decks->complete($request->user(), $deck, $id, $request->integer('duration_seconds')));
    }
}
