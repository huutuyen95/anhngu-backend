<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\SyncSessionsRequest;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Services\ClassSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassSessionController extends Controller
{
    public function __construct(private readonly ClassSessionService $sessions) {}

    public function index(Classroom $classroom): JsonResponse
    {
        return response()->json(['data' => $this->sessions->listForClass($classroom)->values()]);
    }

    public function store(Request $request, Classroom $classroom): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'order' => ['nullable', 'integer'],
            'note' => ['nullable', 'string'],
            'held_on' => ['nullable', 'date'],
        ]);

        $session = $this->sessions->create($classroom, $data);

        return response()->json(['session' => $session], 201);
    }

    public function update(Request $request, ClassSession $session): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'held_on' => ['nullable', 'date'],
        ]);

        return response()->json(['session' => $this->sessions->update($session, $data)]);
    }

    public function destroy(ClassSession $session): JsonResponse
    {
        $this->sessions->delete($session);

        return response()->json(['message' => 'Đã xoá buổi học.']);
    }

    public function sync(SyncSessionsRequest $request, Classroom $classroom): JsonResponse
    {
        $data = $request->validated();
        $result = $this->sessions->sync(
            $classroom,
            $data['sessions'] ?? [],
            $data['deleted_ids'] ?? [],
            $data['force_delete_ids'] ?? [],
        );

        // Có buổi bị chặn xoá (còn nội dung/nhiệm vụ) → 409 để UI hỏi lại.
        if (isset($result['blocked'])) {
            return response()->json([
                'code' => 'delete_blocked',
                'message' => 'Một số tiến trình còn nội dung đã giao — cần xác nhận trước khi xoá.',
                'blocked' => $result['blocked'],
            ], 409);
        }

        return response()->json($result);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:class_sessions,id'],
        ]);

        $this->sessions->reorder($data['ids']);

        return response()->json(['message' => 'Đã cập nhật thứ tự.']);
    }
}
