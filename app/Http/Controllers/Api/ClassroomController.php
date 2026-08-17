<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\DeleteClassroomRequest;
use App\Http\Requests\Classroom\ListClassroomsRequest;
use App\Http\Requests\Classroom\StoreClassroomRequest;
use App\Http\Requests\Classroom\UpdateClassroomRequest;
use App\Http\Resources\ClassroomResource;
use App\Http\Responses\ApiResponse;
use App\Models\Classroom;
use App\Services\ClassroomOverviewService;
use App\Services\ClassroomService;
use Illuminate\Http\JsonResponse;

class ClassroomController extends Controller
{
    public function __construct(private readonly ClassroomService $classrooms) {}

    public function index(ListClassroomsRequest $request): JsonResponse
    {
        $page = $this->classrooms->paginate($request->validated());

        return ApiResponse::paginated(ClassroomResource::collection($page->items()), $page);
    }

    public function store(StoreClassroomRequest $request): JsonResponse
    {
        $result = $this->classrooms->create($request->validated(), $request->user());

        return ApiResponse::resource(new ClassroomResource($result['classroom']), 'classroom', 201, [
            'warning' => $result['warning'],
        ]);
    }

    public function show(Classroom $classroom): JsonResponse
    {
        return ApiResponse::resource(new ClassroomResource($classroom), 'classroom');
    }

    public function overview(Classroom $classroom, ClassroomOverviewService $overview): JsonResponse
    {
        return response()->json($overview->forClass($classroom));
    }

    public function update(UpdateClassroomRequest $request, Classroom $classroom): JsonResponse
    {
        $updated = $this->classrooms->update($classroom, $request->validated());

        return ApiResponse::resource(new ClassroomResource($updated), 'classroom');
    }

    public function destroy(DeleteClassroomRequest $request, Classroom $classroom): JsonResponse
    {
        $studentCount = $this->classrooms->studentCount($classroom);

        if ($studentCount > 0 && ! $request->boolean('confirm')) {
            return response()->json([
                'code' => 'needs_confirm',
                'message' => "Lớp còn {$studentCount} học viên. Xác nhận để gỡ họ khỏi lớp (không xoá tài khoản).",
                'students_count' => $studentCount,
            ], 409);
        }

        $this->classrooms->delete($classroom);

        return ApiResponse::message('Đã xoá lớp học.');
    }
}
