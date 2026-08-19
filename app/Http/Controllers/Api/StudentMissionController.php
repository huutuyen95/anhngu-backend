<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\ListMissionsRequest;
use App\Http\Requests\Mission\StoreMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Services\StudentMissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Màn "Nhiệm vụ" của học viên — chỉ các nhiệm vụ em TỰ THÊM từ Thư viện
 * (`source='self'`). Bài cô giao thuộc "Lớp của em", không đi qua đây.
 */
class StudentMissionController extends Controller
{
    public function __construct(private readonly StudentMissionService $missions) {}

    /** Hai tab: `upcoming` (7 ngày tới) và `done` (đã hoàn thành). */
    public function index(ListMissionsRequest $request): JsonResponse
    {
        $missions = $this->missions->list($request->user(), $request->tab());

        return response()->json([
            'data' => MissionResource::collection($missions),
            'target_days' => StudentMissionService::TARGET_DAYS,
        ]);
    }

    /** Thêm một nội dung vào nhiệm vụ (hạn 7 ngày). */
    public function store(StoreMissionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $mission = $this->missions->add($request->user(), $data['type'], (int) $data['id']);

        return response()->json(['mission' => new MissionResource($mission)], 201);
    }

    public function destroy(Request $request, Mission $mission): JsonResponse
    {
        $this->missions->remove($request->user(), $mission);

        return response()->json(['message' => 'Đã gỡ khỏi nhiệm vụ.']);
    }
}
