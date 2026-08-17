<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\ListDocumentsRequest;
use App\Http\Requests\Document\PublishDocumentRequest;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function index(ListDocumentsRequest $request): JsonResponse
    {
        $page = $this->documents->paginate($request->validated());

        return ApiResponse::paginated(DocumentResource::collection($page->items()), $page);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $document = $this->documents->detail($this->documents->create($request->validated(), $request->user()));

        return ApiResponse::resource(new DocumentResource($document), 'document', 201);
    }

    public function show(Document $document): JsonResponse
    {
        return ApiResponse::resource(new DocumentResource($this->documents->detail($document)), 'document');
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        return ApiResponse::resource(new DocumentResource($this->documents->detail($this->documents->update($document, $request->validated()))), 'document');
    }

    public function publish(PublishDocumentRequest $request, Document $document): JsonResponse
    {
        $document = $this->documents->publish($document, $request->boolean('is_published'));

        return response()->json(['is_published' => $document->is_published]);
    }

    public function destroy(Document $document): JsonResponse
    {
        return ApiResponse::message('Đã xoá nội dung.', additional: [
            'sessions' => $this->documents->delete($document),
        ]);
    }

    public function storageUsage(): JsonResponse
    {
        return response()->json($this->documents->storageUsage());
    }
}
