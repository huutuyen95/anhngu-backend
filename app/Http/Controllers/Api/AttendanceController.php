<?php

namespace App\Http\Controllers\Api;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\BulkAttendanceRequest;
use App\Models\ClassSession;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(ClassSession $session): JsonResponse
    {
        return response()->json(['data' => $this->attendance->forSession($session)]);
    }

    public function bulk(BulkAttendanceRequest $request, ClassSession $session): JsonResponse
    {
        $data = $request->validated();

        $count = $this->attendance->bulkUpsert($session, $data['items'], $request->user());

        return response()->json(['saved' => $count]);
    }

    public function export(ClassSession $session): BinaryFileResponse
    {
        $rows = $this->attendance->forSession($session);

        return Excel::download(new AttendanceExport($rows), "nhan-xet-buoi-{$session->id}.xlsx");
    }
}
