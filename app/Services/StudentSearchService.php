<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\StudentSearchRepository;

class StudentSearchService
{
    private const LIMIT = 6;

    private const SKILL_LABEL = [
        'reading' => 'Đọc', 'listening' => 'Nghe', 'writing' => 'Viết',
        'speaking' => 'Nói', 'mixed' => 'Trắc nghiệm',
    ];

    public function __construct(private readonly StudentSearchRepository $search) {}

    /** @return array<string,mixed> */
    public function search(User $user, string $q): array
    {
        return [
            'tests' => $this->search->tests($q, self::LIMIT)->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'subtitle' => 'Đề · '.(self::SKILL_LABEL[$t->skill?->value] ?? 'Đề thi'),
                'url' => "/library/tests/{$t->id}",
            ])->all(),

            'cards' => $this->search->cards($user, $q, self::LIMIT)->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->term,
                'subtitle' => trim(($c->meaning ? $c->meaning.' · ' : '').'trong '.($c->deck?->name ?? 'bộ từ')),
                'url' => "/library/vocab/{$c->deck_id}?term={$c->id}",
            ])->all(),

            'decks' => $this->search->decks($user, $q, self::LIMIT)->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->name,
                'subtitle' => 'Bộ từ vựng',
                'url' => "/library/vocab/{$d->id}",
            ])->all(),

            'documents' => $this->search->documents($user, $q, self::LIMIT)->map(fn ($d) => [
                'id' => $d->id,
                'title' => $d->title,
                'subtitle' => $d->type === 'lecture' ? 'Bài giảng' : 'Tài liệu',
                'url' => "/library/documents/{$d->id}",
            ])->all(),
        ];
    }
}
