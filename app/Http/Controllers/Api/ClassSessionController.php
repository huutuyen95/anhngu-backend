<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\ReorderClassSessionsRequest;
use App\Http\Requests\Classroom\StoreClassSessionRequest;
use App\Http\Requests\Classroom\SyncSessionsRequest;
use App\Http\Requests\Classroom\UpdateClassSessionRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Services\ClassSessionService;
use Illuminate\Http\JsonResponse;

class ClassSessionController extends Controller
{
    public function __construct(private readonly ClassSessionService $sessions) {}

    public function index(Classroom $classroom): JsonResponse
    {
        return response()->json(['data' => $this->sessions->listForClass($classroom)->values()]);
    }

    public function store(StoreClassSessionRequest $request, Classroom $classroom): JsonResponse
    {
        $data = $request->validated();

        $session = $this->sessions->create($classroom, $data);

        return response()->json(['session' => $session], 201);
    }

    public function update(UpdateClassSessionRequest $request, ClassSession $session): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['session' => $this->sessions->update($session, $data)]);
    }

    public function destroy(ClassSession $session): JsonResponse
    {
        $this->sessions->delete($session);

        return ApiResponse::message('Đã xoá buổi học.');
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

    public function reorder(ReorderClassSessionsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->sessions->reorder($data['ids']);

        return ApiResponse::message('Đã cập nhật thứ tự.');
    }
}
