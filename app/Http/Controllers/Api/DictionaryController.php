<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserVocab;
use App\Services\DictionaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DictionaryController extends Controller
{
    public function __construct(private readonly DictionaryService $dict) {}

    public function lookup(Request $request): JsonResponse
    {
        $word = (string) $request->input('word', '');
        $result = $this->dict->lookup($word);

        if (! $result) {
            return response()->json(['found' => false, 'word' => strtolower(trim($word))]);
        }

        return response()->json(['found' => true] + $result);
    }

    public function saveVocab(Request $request): JsonResponse
    {
        $data = $request->validate([
            'word' => ['required', 'string', 'max:80'],
            'meaning' => ['nullable', 'string', 'max:255'],
            'ipa' => ['nullable', 'string', 'max:120'],
        ]);

        $vocab = UserVocab::updateOrCreate(
            ['user_id' => $request->user()->id, 'word' => strtolower(trim($data['word']))],
            ['meaning' => $data['meaning'] ?? null, 'ipa' => $data['ipa'] ?? null, 'created_at' => now()],
        );

        return response()->json(['saved' => true, 'word' => $vocab->word]);
    }
}
