<?php

namespace App\Http\Controllers\Api;

use App\Exports\AttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(ClassSession $session): JsonResponse
    {
        return response()->json(['data' => $this->attendance->forSession($session)]);
    }

    public function bulk(Request $request, ClassSession $session): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.user_id' => ['required', 'integer'],
            'items.*.status' => ['required', Rule::in(['on_time', 'late', 'absent'])],
            'items.*.comment' => ['nullable', 'string', 'max:500'],
        ]);

        $count = $this->attendance->bulkUpsert($session, $data['items'], $request->user());

        return response()->json(['saved' => $count]);
    }

    public function export(ClassSession $session): BinaryFileResponse
    {
        $rows = $this->attendance->forSession($session);

        return Excel::download(new AttendanceExport($rows), "nhan-xet-buoi-{$session->id}.xlsx");
    }
}
