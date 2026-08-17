<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\ListDocumentsRequest;
use App\Http\Requests\Document\PublishDocumentRequest;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function index(ListDocumentsRequest $request): JsonResponse
    {
        $page = $this->documents->paginate($request->validated());

        return response()->json([
            'data' => DocumentResource::collection($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
                'per_page' => $page->perPage(), 'from' => $page->firstItem(), 'to' => $page->lastItem()],
        ]);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $document = $this->documents->detail($this->documents->create($request->validated(), $request->user()));

        return response()->json(['document' => new DocumentResource($document)], 201);
    }

    public function show(Document $document): JsonResponse
    {
        return response()->json(['document' => new DocumentResource($this->documents->detail($document))]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        return response()->json(['document' => new DocumentResource($this->documents->detail($this->documents->update($document, $request->validated())))]);
    }

    public function publish(PublishDocumentRequest $request, Document $document): JsonResponse
    {
        $document = $this->documents->publish($document, $request->boolean('is_published'));

        return response()->json(['is_published' => $document->is_published]);
    }

    public function destroy(Document $document): JsonResponse
    {
        return response()->json(['message' => 'Đã xoá nội dung.', 'sessions' => $this->documents->delete($document)]);
    }

    public function storageUsage(): JsonResponse
    {
        return response()->json($this->documents->storageUsage());
    }
}
