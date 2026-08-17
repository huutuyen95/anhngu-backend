<?php

namespace App\Services;

use App\Enums\Skill;
use App\Models\Deck;
use App\Models\SessionItem;
use App\Repositories\SessionItemRepository;
use Illuminate\Support\Collection;

class SessionItemService
{
    public function __construct(private readonly SessionItemRepository $repository) {}

    public function list(int $sessionId): Collection
    {
        return $this->repository->forSession($sessionId)->map(function (SessionItem $item): array {
            $counts = $this->repository->missionCounts($item);
            $model = $item->itemable;

            return [
                'id' => $item->id,
                'type' => $item->itemable_type,
                'itemable_id' => $item->itemable_id,
                'title' => $model?->title ?? $model?->name ?? 'Nội dung',
                'meta' => $this->meta($item->itemable_type, $model),
                'assigned' => $counts['total'],
                'done' => $counts['done'],
            ];
        });
    }

    public function delete(SessionItem $item): void
    {
        $this->repository->delete($item);
    }

    private function meta(string $type, mixed $model): string
    {
        if ($type === 'test' && $model) {
            $skill = $model->skill instanceof Skill ? $model->skill->value : $model->skill;

            return trim(($skill ? ucfirst((string) $skill).' · ' : '').($model->duration_minutes ? $model->duration_minutes.' phút' : ''), ' ·');
        }
        if ($type === 'deck' && $model instanceof Deck) {
            return $this->repository->deckCardCount($model).' từ';
        }

        return '';
    }
}
