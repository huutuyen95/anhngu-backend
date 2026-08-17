<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dictionary\LookupDictionaryRequest;
use App\Http\Requests\Dictionary\SaveVocabRequest;
use App\Services\DictionaryService;
use Illuminate\Http\JsonResponse;

class DictionaryController extends Controller
{
    public function __construct(private readonly DictionaryService $dictionary) {}

    public function lookup(LookupDictionaryRequest $request): JsonResponse
    {
        $word = (string) ($request->validated('word') ?? '');
        $result = $this->dictionary->lookup($word);

        return $result ? response()->json(['found' => true] + $result) : response()->json(['found' => false, 'word' => strtolower(trim($word))]);
    }

    public function saveVocab(SaveVocabRequest $request): JsonResponse
    {
        $vocab = $this->dictionary->saveVocab($request->user()->id, $request->validated());

        return response()->json(['saved' => true, 'word' => $vocab->word]);
    }
}
