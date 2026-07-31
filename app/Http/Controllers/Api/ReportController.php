<?php

namespace App\Http\Controllers\Api;

use App\Exports\ClassReportExport;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function show(Request $request, Classroom $classroom): JsonResponse
    {
        $period = $request->input('period', '30d');

        return response()->json($this->reports->classReport($classroom, $period));
    }

    public function export(Request $request, Classroom $classroom): BinaryFileResponse
    {
        $report = $this->reports->classReport($classroom, $request->input('period', '30d'));

        return Excel::download(
            new ClassReportExport($report['by_student']),
            "bao-cao-lop-{$classroom->id}.xlsx",
        );
    }
}
