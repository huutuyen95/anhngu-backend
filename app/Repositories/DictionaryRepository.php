<?php

namespace App\Repositories;

use App\Models\IpaEntry;
use App\Models\UserVocab;

class DictionaryRepository
{
    public function findIpa(string $word): ?IpaEntry
    {
        return IpaEntry::where('word', $word)->first();
    }

    public function saveVocab(int $userId, array $data): UserVocab
    {
        return UserVocab::updateOrCreate(
            ['user_id' => $userId, 'word' => strtolower(trim($data['word']))],
            ['meaning' => $data['meaning'] ?? null, 'ipa' => $data['ipa'] ?? null, 'created_at' => now()],
        );
    }
}
