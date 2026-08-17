<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\ClassReportRequest;
use App\Models\Classroom;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function show(ClassReportRequest $request, Classroom $classroom): JsonResponse
    {
        $period = $request->validated('period', '30d');

        return response()->json($this->reports->classReport($classroom, $period));
    }

    public function export(ClassReportRequest $request, Classroom $classroom): BinaryFileResponse
    {
        return $this->reports->export($classroom, $request->validated('period', '30d'));
    }
}
