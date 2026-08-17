<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assignment\StoreAssignmentRequest;
use App\Models\Classroom;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;

class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments) {}

    public function store(StoreAssignmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $classroom = $this->assignments->classroom($data['classroom_id']);
        $result = $this->assignments->assign($classroom, $data, $request->user());

        return response()->json($result, 201);
    }

    public function remind(Classroom $classroom): JsonResponse
    {
        $count = $this->assignments->remind($classroom);

        return response()->json(['reminded' => $count]);
    }
}
