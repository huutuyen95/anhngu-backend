<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Models\ActivityLog;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDeckController extends Controller
{
    /** Thư viện: chỉ bộ is_published + (dùng chung HOẶC thuộc lớp HS đang học). */
    public function library(Request $request): JsonResponse
    {
        $user = $request->user();
        $classIds = $user->classes()->pluck('classrooms.id');

        $decks = Deck::query()
            ->where('is_published', true)
            ->where(function ($q) use ($classIds) {
                $q->whereDoesntHave('classrooms')
                    ->orWhereHas('classrooms', fn ($c) => $c->whereIn('classrooms.id', $classIds));
            })
            ->withCount([
                'cards',
                'cards as learned_count' => fn ($q) => $q->whereHas(
                    'progress',
                    fn ($p) => $p->where('user_id', $user->id)->whereIn('status', ['learning', 'known']),
                ),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $decks->map(fn (Deck $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'cards_count' => $d->cards_count,
                'learned_count' => $d->learned_count,
            ]),
        ]);
    }

    /** Dữ liệu học 1 bộ: thẻ + tiến độ của chính HS + cấu hình TTS. */
    public function study(Request $request, Deck $deck): JsonResponse
    {
        $cards = $deck->cards;
        $progress = CardProgress::where('user_id', $request->user()->id)
            ->whereIn('card_id', $cards->pluck('id'))
            ->pluck('status', 'card_id');

        $cards->each(function (Card $c) use ($progress) {
            $c->progress_status = $progress[$c->id] ?? 'new';
        });

        return response()->json([
            'deck' => [
                'id' => $deck->id,
                'name' => $deck->name,
                'tts_voice' => $deck->tts_voice,
                'tts_rate' => (float) $deck->tts_rate,
                'tts_repeat' => $deck->tts_repeat,
            ],
            'cards' => CardResource::collection($cards),
        ]);
    }

    public function progress(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:new,learning,known']]);

        $progress = CardProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'card_id' => $card->id],
            ['status' => $data['status'], 'reviewed_at' => now()],
        );
        $progress->increment('review_count');

        return response()->json(['status' => $progress->status]);
    }

    public function sessionComplete(Request $request, Deck $deck): JsonResponse
    {
        $data = $request->validate(['duration_seconds' => ['nullable', 'integer', 'min:0']]);
        $user = $request->user();

        ActivityLog::create([
            'user_id' => $user->id,
            'type' => 'deck_study',
            'subject' => $deck->name,
            'duration_seconds' => $data['duration_seconds'] ?? 0,
            'meta' => ['deck_id' => $deck->id],
            'created_at' => now(),
        ]);

        $cardIds = $deck->cards()->pluck('id');
        $total = $cardIds->count();
        $known = CardProgress::where('user_id', $user->id)
            ->whereIn('card_id', $cardIds)
            ->where('status', 'known')
            ->count();

        $missionDone = false;
        $completePct = (int) setting('content.deck_complete_pct', 80) / 100;
        if ($total > 0 && $known / $total >= $completePct) {
            $missions = Mission::where('user_id', $user->id)
                ->where('missionable_type', $deck->getMorphClass())
                ->where('missionable_id', $deck->id)
                ->where('status', '!=', 'done')
                ->get();
            foreach ($missions as $mission) {
                $mission->update(['status' => 'done', 'completed_at' => now()]);
                $missionDone = true;
            }
        }

        return response()->json([
            'known' => $known,
            'total' => $total,
            'mission_done' => $missionDone,
        ]);
    }
}
