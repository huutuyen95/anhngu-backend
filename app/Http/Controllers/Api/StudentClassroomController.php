<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\StudentRoadmapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentClassroomController extends Controller
{
    public function __construct(private readonly StudentRoadmapService $roadmap) {}

    /** Danh sách lớp của em. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->roadmap->myClassrooms($request->user())]);
    }

    /** Lộ trình một lớp (chỉ lớp em thuộc về). */
    public function roadmap(Request $request, Classroom $classroom): JsonResponse
    {
        if ($request->user()->cannot('viewRoadmap', $classroom)) {
            abort(403, 'Em không ở trong lớp này.');
        }

        return response()->json($this->roadmap->roadmap($classroom, $request->user()));
    }
}
