<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'category_id' => $this->category_id,
            'thumbnail_url' => $this->thumbnail_url,
            'excerpt' => $this->excerpt,
            'reading_minutes' => $this->reading_minutes,
            'is_published' => (bool) $this->is_published,
            'view_count' => $this->view_count,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'attachments_count' => $this->when($this->attachments_count !== null, fn () => (int) $this->attachments_count),
            'classrooms' => $this->whenLoaded('classrooms', fn () => $this->classrooms->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()),
            'classroom_ids' => $this->whenLoaded('classrooms', fn () => $this->classrooms->pluck('id')->values()),
            // body chỉ trả ở chi tiết / đọc (không trả ở danh sách cho nhẹ).
            'body' => $this->when($request->route()?->getActionMethod() !== 'index', fn () => $this->body),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
