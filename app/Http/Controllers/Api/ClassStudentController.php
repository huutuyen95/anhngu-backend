<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Classroom;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassStudentController extends Controller
{
    public function __construct(private readonly StudentService $students) {}

    public function index(Classroom $classroom): JsonResponse
    {
        $students = $classroom->students()
            ->withCount(['testAttempts as in_progress_attempts_count' => fn ($q) => $q->where('status', 'in_progress')])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => StudentResource::collection($students)]);
    }

    public function store(Request $request, Classroom $classroom): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // syncWithoutDetaching để không nhân đôi khi HS đã ở lớp.
        $payload = collect($data['user_ids'])->mapWithKeys(fn ($id) => [$id => ['status' => 'studying']])->all();
        $classroom->students()->syncWithoutDetaching($payload);

        return response()->json(['added' => count($data['user_ids'])]);
    }

    public function quick(Request $request, Classroom $classroom): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
        ]);

        $result = $this->students->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'classroom_ids' => [$classroom->id],
        ]);

        return response()->json([
            'student' => new StudentResource($result['user']),
            'temp_password' => $result['password'],
        ], 201);
    }

    public function destroy(Classroom $classroom, int $userId): JsonResponse
    {
        $classroom->students()->detach($userId);

        return response()->json(['message' => 'Đã gỡ học viên khỏi lớp.']);
    }
}
