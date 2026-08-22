<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Illuminate\Notifications\DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'kind' => $data['kind'] ?? 'mission',
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'url' => $data['url'] ?? null,
            'actor_name' => $data['actor_name'] ?? null,
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
