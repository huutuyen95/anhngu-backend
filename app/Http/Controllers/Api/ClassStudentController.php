<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\AttachStudentsRequest;
use App\Http\Requests\Classroom\QuickCreateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Classroom;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;

class ClassStudentController extends Controller
{
    public function __construct(private readonly StudentService $students) {}

    public function index(Classroom $classroom): JsonResponse
    {
        $students = $this->students->classroomStudents($classroom);

        return ApiResponse::collection(StudentResource::collection($students));
    }

    public function store(AttachStudentsRequest $request, Classroom $classroom): JsonResponse
    {
        $data = $request->validated();
        $this->students->attachToClassroom($classroom, $data['user_ids']);

        return response()->json(['added' => count($data['user_ids'])]);
    }

    public function quick(QuickCreateStudentRequest $request, Classroom $classroom): JsonResponse
    {
        $data = $request->validated();

        $result = $this->students->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'classroom_ids' => [$classroom->id],
        ]);

        return ApiResponse::resource(new StudentResource($result['user']), 'student', 201, [
            'temp_password' => $result['password'],
        ]);
    }

    public function destroy(Classroom $classroom, int $userId): JsonResponse
    {
        $this->students->detachFromClassroom($classroom, $userId);

        return ApiResponse::message('Đã gỡ học viên khỏi lớp.');
    }
}
