<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\StoreClassroomRequest;
use App\Http\Requests\Classroom\UpdateClassroomRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use App\Services\ClassroomOverviewService;
use App\Services\ClassroomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function __construct(private readonly ClassroomService $classrooms) {}

    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $page = Classroom::query()
            ->when($request->input('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->input('status'), function ($q, $status) use ($today) {
                match ($status) {
                    'upcoming' => $q->whereNotNull('starts_on')->whereDate('starts_on', '>', $today),
                    'ended' => $q->whereNotNull('ends_on')->whereDate('ends_on', '<', $today),
                    'active' => $q->where(fn ($s) => $s->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today))
                        ->where(fn ($s) => $s->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today)),
                    default => $q,
                };
            })
            ->latest()
            ->paginate((int) $request->input('per_page', 24));

        return response()->json([
            'data' => ClassroomResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreClassroomRequest $request): JsonResponse
    {
        $result = $this->classrooms->create($request->validated(), $request->user());

        return response()->json([
            'classroom' => new ClassroomResource($result['classroom']),
            'warning' => $result['warning'],
        ], 201);
    }

    public function show(Classroom $classroom): JsonResponse
    {
        return response()->json(['classroom' => new ClassroomResource($classroom)]);
    }

    public function overview(Classroom $classroom, ClassroomOverviewService $overview): JsonResponse
    {
        return response()->json($overview->forClass($classroom));
    }

    public function update(UpdateClassroomRequest $request, Classroom $classroom): JsonResponse
    {
        $updated = $this->classrooms->update($classroom, $request->validated());

        return response()->json(['classroom' => new ClassroomResource($updated)]);
    }

    public function destroy(Request $request, Classroom $classroom): JsonResponse
    {
        $studentCount = $classroom->students()->count();

        if ($studentCount > 0 && ! $request->boolean('confirm')) {
            return response()->json([
                'code' => 'needs_confirm',
                'message' => "Lớp còn {$studentCount} học viên. Xác nhận để gỡ họ khỏi lớp (không xoá tài khoản).",
                'students_count' => $studentCount,
            ], 409);
        }

        $this->classrooms->delete($classroom);

        return response()->json(['message' => 'Đã xoá lớp học.']);
    }
}
