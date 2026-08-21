<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentReportRequest;
use App\Services\StudentReportService;
use Illuminate\Http\JsonResponse;

class StudentReportController extends Controller
{
    public function __construct(private readonly StudentReportService $service) {}

    /** Báo cáo học sinh — scope=overview (mọi lớp) | class (1 lớp). */
    public function show(StudentReportRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->service->report(
            $request->user(),
            $data['scope'] ?? 'overview',
            $data['classroom_id'] ?? null,
            $data['period'] ?? '30d',
        ));
    }
}
