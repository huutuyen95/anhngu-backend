<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'integer', 'exists:classrooms,id'],
            'class_session_id' => ['required', 'integer', 'exists:class_sessions,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', Rule::in(['test', 'writing', 'deck'])],
            'items.*.id' => ['required', 'integer'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
            'due_date' => ['nullable', 'date'],
            'attempts_allowed' => ['nullable', 'integer', 'min:1'],
            'schedule' => ['required', Rule::in(['now', 'at', 'draft'])],
            'scheduled_at' => ['nullable', 'date', 'after:now', 'required_if:schedule,at'],
            'notify' => ['boolean'],
        ], [
            'scheduled_at.after' => 'Thời điểm lên lịch phải ở tương lai.',
        ]);

        $classroom = Classroom::findOrFail($data['classroom_id']);
        $result = $this->assignments->assign($classroom, $data, $request->user());

        return response()->json($result, 201);
    }

    public function remind(Classroom $classroom): JsonResponse
    {
        $count = $this->assignments->remind($classroom);

        return response()->json(['reminded' => $count]);
    }
}
