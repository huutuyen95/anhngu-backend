<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Repositories\DocumentRepository;
use Illuminate\Database\Eloquent\Collection;

class StudentDocumentService
{
    public function __construct(private readonly DocumentRepository $documents) {}

    public function library(User $user, array $filters): Collection
    {
        return $this->documents->studentLibrary($this->documents->classroomIdsForUser($user->id), $filters);
    }

    public function read(User $user, Document $document): array
    {
        $allowed = ($document->is_published && $document->type === 'document')
            || $this->documents->canReadAssigned($document, $this->documents->classroomIdsForUser($user->id));
        abort_unless($allowed, 403, 'Bạn không có quyền xem nội dung này.');

        return $this->documents->readForUser($document, $user->id);
    }

    public function track(User $user, Document $document, int $progress): array
    {
        return $this->documents->trackView($document, $user->id, $progress);
    }
}
