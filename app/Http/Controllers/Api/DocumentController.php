<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $docs) {}

    public function index(Request $request): JsonResponse
    {
        $page = Document::query()
            ->with(['category:id,name'])
            ->withCount('attachments')
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->input('q'), fn ($q, $t) => $q->where('title', 'like', "%{$t}%"))
            ->when($request->input('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->has('is_published') && $request->input('is_published') !== '', fn ($q) => $q->where('is_published', $request->boolean('is_published')))
            ->latest('updated_at')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => DocumentResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateDoc($request);
        $doc = $this->docs->create($data, $request->user());

        return response()->json(['document' => new DocumentResource($doc->load(['category:id,name', 'attachments', 'classrooms:id,name']))], 201);
    }

    public function show(Document $document): JsonResponse
    {
        $document->load(['category:id,name', 'attachments', 'classrooms:id,name']);

        return response()->json(['document' => new DocumentResource($document)]);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        $data = $this->validateDoc($request, updating: true);
        $updated = $this->docs->update($document, $data);

        return response()->json(['document' => new DocumentResource($updated->load(['category:id,name', 'attachments', 'classrooms:id,name']))]);
    }

    public function publish(Request $request, Document $document): JsonResponse
    {
        if ($document->type === 'lecture') {
            return response()->json(['message' => 'Bài giảng không có công tắc thư viện — chỉ đến học sinh qua giao bài.'], 422);
        }
        $data = $request->validate(['is_published' => ['required', 'boolean']]);
        $document->update(['is_published' => $data['is_published']]);

        return response()->json(['is_published' => $document->is_published]);
    }

    public function destroy(Document $document): JsonResponse
    {
        // Không chặn xoá — nhưng trả kèm buổi đang giao để UI cảnh báo.
        $sessions = $this->docs->sessionsUsing($document);
        $document->delete();

        return response()->json(['message' => 'Đã xoá nội dung.', 'sessions' => $sessions]);
    }

    public function storageUsage(): JsonResponse
    {
        return response()->json($this->docs->storageUsage());
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDoc(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'type' => [$updating ? 'sometimes' : 'required', Rule::in(['document', 'lecture'])],
            'title' => [$updating ? 'sometimes' : 'required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:document_categories,id'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'body' => ['nullable', 'string'],
            'classroom_ids' => ['sometimes', 'array'],
            'classroom_ids.*' => ['integer', 'exists:classrooms,id'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
    }
}
