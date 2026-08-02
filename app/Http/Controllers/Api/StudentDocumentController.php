<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentView;
use App\Models\Mission;
use App\Models\SessionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDocumentController extends Controller
{
    /** Thư viện HS: chỉ type=document + published + (dùng chung HOẶC thuộc lớp HS). */
    public function library(Request $request): JsonResponse
    {
        $classIds = $request->user()->classes()->pluck('classrooms.id');

        $docs = Document::query()
            ->where('type', 'document')
            ->where('is_published', true)
            ->where(function ($q) use ($classIds) {
                $q->whereDoesntHave('classrooms')
                    ->orWhereHas('classrooms', fn ($c) => $c->whereIn('classrooms.id', $classIds));
            })
            ->when($request->input('q'), fn ($q, $t) => $q->where('title', 'like', "%{$t}%"))
            ->when($request->input('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->with('category:id,name')
            ->latest('updated_at')
            ->get();

        return response()->json(['data' => DocumentResource::collection($docs)]);
    }

    /** Đọc 1 nội dung: cho phép nếu published-document HOẶC được giao qua buổi cho HS. */
    public function read(Request $request, Document $document): JsonResponse
    {
        if (! $this->canRead($request, $document)) {
            return response()->json(['message' => 'Bạn không có quyền xem nội dung này.'], 403);
        }

        $document->increment('view_count');
        $document->load(['category:id,name', 'attachments']);
        $view = DocumentView::where('document_id', $document->id)->where('user_id', $request->user()->id)->first();

        return response()->json([
            'document' => new DocumentResource($document),
            'my_progress' => $view?->progress_pct ?? 0,
            'completed' => (bool) $view?->completed_at,
        ]);
    }

    public function view(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate(['progress_pct' => ['required', 'integer', 'min:0', 'max:100']]);
        $user = $request->user();

        $view = DocumentView::firstOrNew(['document_id' => $document->id, 'user_id' => $user->id]);
        $view->progress_pct = max($view->progress_pct ?? 0, $data['progress_pct']);
        $completed = false;
        if ($view->progress_pct >= 80 && ! $view->completed_at) {
            $view->completed_at = now();
            $completed = true;
        }
        $view->updated_at = now();
        $view->save();

        // Đánh dấu mission done nếu tài liệu này được giao cho HS.
        $missionDone = false;
        if ($completed) {
            $missions = Mission::where('user_id', $user->id)
                ->where('missionable_type', $document->getMorphClass())
                ->where('missionable_id', $document->id)
                ->where('status', '!=', 'done')->get();
            foreach ($missions as $m) {
                $m->update(['status' => 'done', 'completed_at' => now()]);
                $missionDone = true;
            }
        }

        return response()->json(['progress_pct' => $view->progress_pct, 'completed' => (bool) $view->completed_at, 'mission_done' => $missionDone]);
    }

    private function canRead(Request $request, Document $document): bool
    {
        if ($document->is_published && $document->type === 'document') {
            return true;
        }
        $classIds = $request->user()->classes()->pluck('classrooms.id');

        return SessionItem::where('itemable_type', $document->getMorphClass())
            ->where('itemable_id', $document->id)
            ->whereHas('classSession', fn ($q) => $q->whereIn('classroom_id', $classIds))
            ->exists();
    }
}
