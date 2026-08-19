<?php

namespace App\Http\Resources;

use App\Models\Deck;
use App\Models\Document;
use App\Models\Mission;
use App\Models\Test;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Một nhiệm vụ của học viên, kèm khối `content` ĐÃ CHUẨN HOÁ.
 *
 * FE chỉ dựng MỘT loại thẻ cho mọi loại nội dung: đọc `content.type` để biết nhãn +
 * đường dẫn, còn `title`/`thumbnail_url`/`meta` thì loại nào cũng có. Thêm loại nội
 * dung mới chỉ cần bổ sung một nhánh ở `content()` — FE không phải sửa gì.
 *
 * @mixin Mission
 */
class MissionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'source' => $this->source,
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'content' => $this->content(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function content(): ?array
    {
        $model = $this->missionable;

        if (! $model) {
            return null; // nội dung đã bị xoá — FE ẩn thẻ này đi
        }

        return match (true) {
            $model instanceof Test => $this->testContent($model),
            $model instanceof Deck => $this->deckContent($model),
            $model instanceof Document => $this->documentContent($model),
            default => $this->genericContent($model),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function testContent(Test $test): array
    {
        return [
            'type' => 'test',
            'id' => $test->id,
            'title' => $test->title,
            'thumbnail_url' => $test->thumbnail_url,
            'skill' => $test->skill->value,
            'meta' => array_values(array_filter([
                $test->duration_minutes > 0 ? $test->duration_minutes.' phút' : null,
                $test->questionCount().' câu',
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deckContent(Deck $deck): array
    {
        return [
            'type' => 'deck',
            'id' => $deck->id,
            'title' => $deck->name,
            'thumbnail_url' => null,
            'meta' => [$deck->cards()->count().' từ'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentContent(Document $document): array
    {
        return [
            'type' => 'document',
            'id' => $document->id,
            'title' => $document->title,
            'thumbnail_url' => $document->thumbnail_url,
            'meta' => array_values(array_filter([
                $document->reading_minutes ? $document->reading_minutes.' phút đọc' : null,
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function genericContent(Model $model): array
    {
        return [
            'type' => $model->getMorphClass(),
            'id' => $model->getKey(),
            'title' => (string) ($model->getAttribute('title') ?? $model->getAttribute('name') ?? ''),
            'thumbnail_url' => $model->getAttribute('thumbnail_url'),
            'meta' => [],
        ];
    }
}
