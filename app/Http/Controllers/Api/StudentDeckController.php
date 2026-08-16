<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Models\ActivityLog;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Classroom;
use App\Models\Deck;
use App\Models\Mission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDeckController extends Controller
{
    /** Thư viện: bộ is_published + (dùng chung HOẶC thuộc lớp HS HOẶC đã được giao cho em qua mission). */
    public function library(Request $request): JsonResponse
    {
        $user = $request->user();
        $classIds = $user->classes()->pluck('classrooms.id');
        // Bộ đã giao cho em trong lớp (qua mission) cũng phải thấy được ở Thư viện — cùng 1 bộ,
        // tiến độ tách riêng theo classroom_id.
        $missionDeckIds = Mission::where('user_id', $user->id)
            ->where('missionable_type', (new Deck)->getMorphClass())
            ->pluck('missionable_id');

        $decks = Deck::query()
            ->where('is_published', true)
            ->where(function ($q) use ($classIds, $missionDeckIds) {
                $q->whereDoesntHave('classrooms')
                    ->orWhereHas('classrooms', fn ($c) => $c->whereIn('classrooms.id', $classIds))
                    ->orWhereIn('id', $missionDeckIds);
            })
            ->with([
                'category:id,name,order',
                'classrooms' => fn ($q) => $q
                    ->whereIn('classrooms.id', $classIds)
                    ->select('classrooms.id', 'classrooms.name'),
            ])
            ->withCount([
                'cards',
                // Chỉ đếm tiến độ TỰ LUYỆN Thư viện (classroom_id null) — không lẫn tiến độ học trong lớp.
                'cards as learned_count' => fn ($q) => $q->whereHas(
                    'progress',
                    fn ($p) => $p->where('user_id', $user->id)->whereNull('classroom_id')->whereIn('status', ['learning', 'known']),
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
                'category' => $d->category ? [
                    'id' => $d->category->id,
                    'name' => $d->category->name,
                    'order' => $d->category->order,
                ] : null,
                'classrooms' => $d->classrooms->map(fn (Classroom $classroom) => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                ])->values(),
            ]),
        ]);
    }

    /**
     * Dữ liệu học 1 bộ: thẻ + tiến độ của chính HS (theo SCOPE lớp nếu học trong lớp) + cấu hình TTS.
     * classroom_id: null = tự luyện Thư viện; có giá trị = học trong lớp (tiến độ tách riêng).
     */
    public function study(Request $request, Deck $deck): JsonResponse
    {
        $classroomId = $this->resolveClassroomId($request);
        $cards = $deck->cards;

        $progress = CardProgress::where('user_id', $request->user()->id)
            ->whereIn('card_id', $cards->pluck('id'))
            ->where(fn ($q) => $classroomId === null ? $q->whereNull('classroom_id') : $q->where('classroom_id', $classroomId))
            ->pluck('status', 'card_id');

        $cards->each(function (Card $c) use ($progress) {
            $c->progress_status = $progress[$c->id] ?? 'new';
        });

        $known = $cards->filter(fn (Card $c) => $c->progress_status === 'known')->count();

        return response()->json([
            'deck' => [
                'id' => $deck->id,
                'name' => $deck->name,
                'tts_voice' => $deck->tts_voice,
                'tts_rate' => (float) $deck->tts_rate,
                'tts_repeat' => $deck->tts_repeat,
            ],
            'progress' => ['known' => $known, 'total' => $cards->count()],
            'cards' => CardResource::collection($cards),
        ]);
    }

    public function progress(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:new,learning,known']]);
        $classroomId = $this->resolveClassroomId($request);

        $progress = CardProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'card_id' => $card->id, 'classroom_id' => $classroomId],
            ['status' => $data['status'], 'reviewed_at' => now()],
        );
        $progress->increment('review_count');

        return response()->json(['status' => $progress->status]);
    }

    public function sessionComplete(Request $request, Deck $deck): JsonResponse
    {
        $data = $request->validate(['duration_seconds' => ['nullable', 'integer', 'min:0']]);
        $classroomId = $this->resolveClassroomId($request);
        $user = $request->user();

        ActivityLog::create([
            'user_id' => $user->id,
            'type' => 'deck_study',
            'subject' => $deck->name,
            'duration_seconds' => $data['duration_seconds'] ?? 0,
            'meta' => ['deck_id' => $deck->id, 'classroom_id' => $classroomId],
            'created_at' => now(),
        ]);

        $cardIds = $deck->cards()->pluck('id');
        $total = $cardIds->count();
        $known = CardProgress::where('user_id', $user->id)
            ->whereIn('card_id', $cardIds)
            ->where(fn ($q) => $classroomId === null ? $q->whereNull('classroom_id') : $q->where('classroom_id', $classroomId))
            ->where('status', 'known')
            ->count();

        // Chỉ đánh dấu nhiệm vụ CỦA LỚP khi học TRONG LỚP (tự luyện Thư viện không đụng nhiệm vụ lớp).
        $missionDone = false;
        $completePct = (int) setting('content.deck_complete_pct', 80) / 100;
        if ($classroomId !== null && $total > 0 && $known / $total >= $completePct) {
            $missions = Mission::where('user_id', $user->id)
                ->where('classroom_id', $classroomId)
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

    /**
     * Đọc + xác thực classroom_id trong request. Trả null nếu không có (luồng Thư viện).
     * Có giá trị → HS phải thuộc lớp đó, không thì 403.
     */
    private function resolveClassroomId(Request $request): ?int
    {
        $request->validate(['classroom_id' => ['nullable', 'integer', 'exists:classrooms,id']]);
        $classroomId = $request->input('classroom_id');
        if ($classroomId === null) {
            return null;
        }

        $isMember = Classroom::whereKey($classroomId)
            ->whereHas('students', fn ($q) => $q->whereKey($request->user()->id))
            ->exists();
        abort_unless($isMember, 403, 'Em không ở trong lớp này.');

        return (int) $classroomId;
    }
}
