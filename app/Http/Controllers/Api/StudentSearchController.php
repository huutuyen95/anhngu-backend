<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentSearchRequest;
use App\Services\StudentSearchService;
use Illuminate\Http\JsonResponse;

class StudentSearchController extends Controller
{
    public function __construct(private readonly StudentSearchService $service) {}

    /** Tìm nhanh đề · từ vựng · tài liệu học sinh xem được. */
    public function index(StudentSearchRequest $request): JsonResponse
    {
        return response()->json($this->service->search($request->user(), trim($request->validated()['q'])));
    }
}
