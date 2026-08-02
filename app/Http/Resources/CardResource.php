<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Card
 */
class CardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => $this->order,
            'term' => $this->term,
            'meaning' => $this->meaning,
            'pos' => $this->pos,
            'ipa' => $this->ipa,
            'audio_url' => $this->audio_url,
            'image_url' => $this->image_url,
            'example' => $this->example,
            // Trạng thái học của HS hiện tại (chỉ khi được nạp riêng).
            'progress_status' => $this->when(isset($this->progress_status), fn () => $this->progress_status),
        ];
    }
}
