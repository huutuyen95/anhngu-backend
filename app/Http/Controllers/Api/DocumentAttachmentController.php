<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentAttachmentController extends Controller
{
    public function __construct(private readonly DocumentService $docs) {}

    public function store(Request $request, Document $document): JsonResponse
    {
        $maxKb = (int) setting('storage.max_file_mb', 50) * 1024;
        $request->validate(['file' => ['required', 'file', 'max:'.$maxKb]]);
        $file = $request->file('file');

        if ($file->getSize() > $this->docs->remainingBytes()) {
            return response()->json([
                'code' => 'quota_exceeded',
                'message' => 'Vượt quá dung lượng cho phép (5 GB). Hãy xoá bớt file nặng trước.',
            ], 422);
        }

        $path = $file->store('doc-attachments', 'public');
        $att = $document->attachments()->create([
            'name' => $file->getClientOriginalName(),
            'url' => asset('storage/'.$path),
            'size_bytes' => $file->getSize(),
            'mime' => $file->getMimeType(),
            'order' => (int) ($document->attachments()->max('order') ?? 0) + 1,
            'created_at' => now(),
        ]);

        return response()->json(['attachment' => new AttachmentResource($att)], 201);
    }

    public function destroy(DocumentAttachment $attachment): JsonResponse
    {
        $attachment->delete();

        return response()->json(['message' => 'Đã xoá file.']);
    }

    public function reorder(Request $request, Document $document): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        foreach ($data['ids'] as $i => $id) {
            DocumentAttachment::where('document_id', $document->id)->where('id', $id)->update(['order' => $i + 1]);
        }

        return response()->json(['message' => 'Đã cập nhật thứ tự.']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);
        $count = DocumentAttachment::whereIn('id', $data['ids'])->delete();

        return response()->json(['deleted' => $count]);
    }

    public function thumbnail(Request $request, Document $document): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $path = $request->file('file')->store('doc-thumbs', 'public');
        $document->update(['thumbnail_url' => asset('storage/'.$path)]);

        return response()->json(['thumbnail_url' => $document->thumbnail_url]);
    }
}
