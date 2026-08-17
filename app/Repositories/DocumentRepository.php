<?php

namespace App\Repositories;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\DocumentView;
use App\Models\Mission;
use App\Models\SessionItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DocumentRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Document::query()->with('category:id,name')->withCount('attachments')
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when(array_key_exists('is_published', $filters), fn ($q) => $q->where('is_published', $filters['is_published']))
            ->latest('updated_at')->paginate($filters['per_page'] ?? 20);
    }

    public function create(array $data, array $classroomIds): Document
    {
        return DB::transaction(function () use ($data, $classroomIds) {
            $document = Document::create($data);
            $document->classrooms()->sync($classroomIds);

            return $document;
        });
    }

    public function update(Document $document, array $data, ?array $classroomIds = null): Document
    {
        DB::transaction(function () use ($document, $data, $classroomIds) {
            $document->update($data);
            if ($classroomIds !== null) {
                $document->classrooms()->sync($classroomIds);
            }
        });

        return $document->fresh();
    }

    public function detail(Document $document): Document
    {
        return $document->load(['category:id,name', 'attachments', 'classrooms:id,name']);
    }

    public function slugExists(string $slug): bool
    {
        return Document::withTrashed()->where('slug', $slug)->exists();
    }

    public function delete(Document $document): void
    {
        $document->delete();
    }

    public function sessionsUsing(Document $document): array
    {
        return SessionItem::where('itemable_type', $document->getMorphClass())->where('itemable_id', $document->id)
            ->with('classSession.classroom')->get()->map(fn (SessionItem $item) => [
                'id' => $item->classSession?->id, 'title' => $item->classSession?->title,
                'classroom' => $item->classSession?->classroom?->name,
            ])->values()->all();
    }

    public function attachmentStats(): array
    {
        return [
            'total' => (int) DocumentAttachment::sum('size_bytes'),
            'by_mime' => DocumentAttachment::selectRaw('mime, SUM(size_bytes) as bytes')->groupBy('mime')->get(),
            'biggest' => DocumentAttachment::with('document:id,title')->orderByDesc('size_bytes')->take(10)->get(),
        ];
    }

    public function createAttachment(Document $document, array $data): DocumentAttachment
    {
        $data['order'] = (int) ($document->attachments()->max('order') ?? 0) + 1;

        return $document->attachments()->create($data);
    }

    public function deleteAttachment(DocumentAttachment $attachment): void
    {
        $attachment->delete();
    }

    public function reorderAttachments(Document $document, array $ids): void
    {
        foreach ($ids as $index => $id) {
            DocumentAttachment::where('document_id', $document->id)->whereKey($id)->update(['order' => $index + 1]);
        }
    }

    public function bulkDeleteAttachments(array $ids): int
    {
        return DocumentAttachment::whereIn('id', $ids)->delete();
    }

    public function studentLibrary(array $classIds, array $filters): Collection
    {
        return Document::query()->where('type', 'document')->where('is_published', true)
            ->where(fn ($q) => $q->whereDoesntHave('classrooms')->orWhereHas('classrooms', fn ($c) => $c->whereIn('classrooms.id', $classIds)))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->with('category:id,name')->latest('updated_at')->get();
    }

    public function classroomIdsForUser(int $userId): array
    {
        return DB::table('class_user')->where('user_id', $userId)->pluck('classroom_id')->map(fn ($id) => (int) $id)->all();
    }

    public function canReadAssigned(Document $document, array $classIds): bool
    {
        return SessionItem::where('itemable_type', $document->getMorphClass())->where('itemable_id', $document->id)
            ->whereHas('classSession', fn ($q) => $q->whereIn('classroom_id', $classIds))->exists();
    }

    public function readForUser(Document $document, int $userId): array
    {
        $document->increment('view_count');
        $view = DocumentView::where('document_id', $document->id)->where('user_id', $userId)->first();

        return ['document' => $document->load(['category:id,name', 'attachments']), 'view' => $view];
    }

    public function trackView(Document $document, int $userId, int $progress): array
    {
        return DB::transaction(function () use ($document, $userId, $progress) {
            $view = DocumentView::firstOrNew(['document_id' => $document->id, 'user_id' => $userId]);
            $view->progress_pct = max($view->progress_pct ?? 0, $progress);
            $newlyCompleted = $view->progress_pct >= 80 && ! $view->completed_at;
            if ($newlyCompleted) {
                $view->completed_at = now();
            }
            $view->updated_at = now();
            $view->save();
            $missionDone = false;
            if ($newlyCompleted) {
                $missions = Mission::where('user_id', $userId)->where('missionable_type', $document->getMorphClass())
                    ->where('missionable_id', $document->id)->where('status', '!=', 'done')->get();
                foreach ($missions as $mission) {
                    $mission->update(['status' => 'done', 'completed_at' => now()]);
                    $missionDone = true;
                }
            }

            return ['view' => $view, 'mission_done' => $missionDone];
        });
    }
}
