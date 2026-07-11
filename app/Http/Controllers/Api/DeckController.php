<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardProgress;
use App\Models\Deck;
use Illuminate\Http\Request;

class DeckController extends Controller
{
    public function index(Request $request)
    {
        $decks = Deck::query()
            ->where('is_public', true)
            ->orWhere('owner_id', $request->user()->id)
            ->withCount('cards')
            ->get();

        return response()->json($decks);
    }

    public function show(Deck $deck)
    {
        return response()->json($deck->load('cards'));
    }

    public function saveProgress(Request $request, Deck $deck)
    {
        $data = $request->validate([
            'card_id' => ['required', 'integer', 'exists:cards,id'],
            'status' => ['required', 'string', 'in:new,learning,known'],
        ]);

        $progress = CardProgress::where('user_id', $request->user()->id)
            ->where('card_id', $data['card_id'])
            ->first();

        $progress = CardProgress::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'card_id' => $data['card_id'],
            ],
            [
                'status' => $data['status'],
                'review_count' => ($progress->review_count ?? 0) + 1,
            ]
        );

        return response()->json($progress);
    }
}
