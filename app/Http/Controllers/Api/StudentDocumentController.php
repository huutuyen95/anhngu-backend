<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\ListStudentDocumentsRequest;
use App\Http\Requests\Document\TrackDocumentViewRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Document;
use App\Services\StudentDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDocumentController extends Controller
{
    public function __construct(private readonly StudentDocumentService $documents) {}

    public function library(ListStudentDocumentsRequest $request): JsonResponse
    {
        return ApiResponse::collection(DocumentResource::collection($this->documents->library($request->user(), $request->validated())));
    }

    public function read(Request $request, Document $document): JsonResponse
    {
        $result = $this->documents->read($request->user(), $document);

        return ApiResponse::resource(new DocumentResource($result['document']), 'document', additional: [
            'my_progress' => $result['view']?->progress_pct ?? 0,
            'completed' => (bool) $result['view']?->completed_at,
        ]);
    }

    public function view(TrackDocumentViewRequest $request, Document $document): JsonResponse
    {
        $result = $this->documents->track($request->user(), $document, $request->integer('progress_pct'));

        return response()->json([
            'progress_pct' => $result['view']->progress_pct,
            'completed' => (bool) $result['view']->completed_at,
            'mission_done' => $result['mission_done'],
        ]);
    }
}
