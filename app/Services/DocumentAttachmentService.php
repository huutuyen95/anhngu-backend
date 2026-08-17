<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Repositories\DocumentRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;

class DocumentAttachmentService
{
    public function __construct(private readonly DocumentRepository $documents) {}

    public function upload(Document $document, UploadedFile $file): DocumentAttachment
    {
        $remaining = max(0, DocumentService::QUOTA_BYTES - $this->documents->attachmentStats()['total']);
        if ($file->getSize() > $remaining) {
            throw new HttpResponseException(response()->json([
                'code' => 'quota_exceeded',
                'message' => 'Vượt quá dung lượng cho phép (5 GB). Hãy xoá bớt file nặng trước.',
            ], 422));
        }
        $path = $file->store('doc-attachments', 'public');

        return $this->documents->createAttachment($document, [
            'name' => $file->getClientOriginalName(), 'url' => asset('storage/'.$path),
            'size_bytes' => $file->getSize(), 'mime' => $file->getMimeType(), 'created_at' => now(),
        ]);
    }

    public function delete(DocumentAttachment $attachment): void
    {
        $this->documents->deleteAttachment($attachment);
    }

    public function reorder(Document $document, array $ids): void
    {
        $this->documents->reorderAttachments($document, $ids);
    }

    public function bulkDelete(array $ids): int
    {
        return $this->documents->bulkDeleteAttachments($ids);
    }

    public function thumbnail(Document $document, UploadedFile $file): string
    {
        $path = $file->store('doc-thumbs', 'public');

        return $this->documents->update($document, ['thumbnail_url' => asset('storage/'.$path)])->thumbnail_url;
    }
}
