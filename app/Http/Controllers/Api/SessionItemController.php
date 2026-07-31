<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\SessionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessionId = $request->integer('session_id');

        $items = SessionItem::where('class_session_id', $sessionId)
            ->with('itemable')
            ->orderBy('order')
            ->get()
            ->map(function (SessionItem $item) {
                $model = $item->itemable;
                $missions = Mission::where('class_session_id', $item->class_session_id)
                    ->where('missionable_type', $item->itemable_type)
                    ->where('missionable_id', $item->itemable_id);
                $total = (clone $missions)->count();
                $done = (clone $missions)->where('status', 'done')->count();

                return [
                    'id' => $item->id,
                    'type' => $item->itemable_type,
                    'itemable_id' => $item->itemable_id,
                    'title' => $model?->title ?? $model?->name ?? 'Nội dung',
                    'meta' => $this->meta($item->itemable_type, $model),
                    'assigned' => $total,
                    'done' => $done,
                ];
            });

        return response()->json(['data' => $items]);
    }

    public function destroy(SessionItem $sessionItem): JsonResponse
    {
        $sessionItem->delete();

        return response()->json(['message' => 'Đã gỡ nội dung khỏi buổi.']);
    }

    private function meta(string $type, $model): string
    {
        if ($type === 'test' && $model) {
            $skill = $model->skill instanceof \App\Enums\Skill ? $model->skill->value : $model->skill;

            return trim(($skill ? ucfirst((string) $skill).' · ' : '').($model->duration_minutes ? $model->duration_minutes.' phút' : ''), ' ·');
        }
        if ($type === 'deck' && $model) {
            return ($model->cards()->count() ?? 0).' từ';
        }

        return '';
    }
}
