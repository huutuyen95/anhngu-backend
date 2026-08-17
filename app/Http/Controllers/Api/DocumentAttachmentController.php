<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\BulkDeleteAttachmentsRequest;
use App\Http\Requests\Document\ReorderAttachmentsRequest;
use App\Http\Requests\Document\UploadAttachmentRequest;
use App\Http\Requests\Document\UploadDocumentThumbnailRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Services\DocumentAttachmentService;
use Illuminate\Http\JsonResponse;

class DocumentAttachmentController extends Controller
{
    public function __construct(private readonly DocumentAttachmentService $attachments) {}

    public function store(UploadAttachmentRequest $request, Document $document): JsonResponse
    {
        return ApiResponse::resource(new AttachmentResource($this->attachments->upload($document, $request->file('file'))), 'attachment', 201);
    }

    public function destroy(DocumentAttachment $attachment): JsonResponse
    {
        $this->attachments->delete($attachment);

        return ApiResponse::message('Đã xoá file.');
    }

    public function reorder(ReorderAttachmentsRequest $request, Document $document): JsonResponse
    {
        $this->attachments->reorder($document, $request->validated('ids'));

        return ApiResponse::message('Đã cập nhật thứ tự.');
    }

    public function bulkDelete(BulkDeleteAttachmentsRequest $request): JsonResponse
    {
        return response()->json(['deleted' => $this->attachments->bulkDelete($request->validated('ids'))]);
    }

    public function thumbnail(UploadDocumentThumbnailRequest $request, Document $document): JsonResponse
    {
        return response()->json(['thumbnail_url' => $this->attachments->thumbnail($document, $request->file('file'))]);
    }
}
