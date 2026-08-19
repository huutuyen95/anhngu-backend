<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Test\ListStudentTestsRequest;
use App\Http\Resources\MissionResource;
use App\Http\Resources\StudentTestResource;
use App\Http\Resources\TestDetailResource;
use App\Http\Responses\ApiResponse;
use App\Models\Test;
use App\Services\StudentMissionService;
use App\Services\StudentTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function __construct(
        private readonly StudentTestService $tests,
        private readonly StudentMissionService $missions,
    ) {}

    public function index(ListStudentTestsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $student = $request->user();

        $page = $this->tests->list($filters, $student);

        return ApiResponse::paginated(
            StudentTestResource::collection($page->items()),
            $page,
            $this->tests->summary($filters, $student),
        );
    }

    public function show(Request $request, Test $test): JsonResponse
    {
        $detail = $this->tests->detail($test);

        // `mission` để nút "Thêm vào nhiệm vụ" ở trang chi tiết biết đang ở trạng thái nào
        // (chưa thêm / đã thêm / đã xong) mà không phải gọi thêm API.
        //
        // Ghép thẳng vào mảng chứ KHÔNG dùng ->additional(): resource này không có wrap,
        // gọi additional() sẽ bọc toàn bộ payload vào "data" và làm vỡ mọi chỗ đang đọc
        // `parts[...]` (cả FE lẫn test).
        $mission = $this->missions->findFor($request->user(), $detail);
        $payload = (new TestDetailResource($detail, revealAnswers: false))->resolve($request);
        $payload['mission'] = $mission ? (new MissionResource($mission))->resolve($request) : null;
        // Lượt em đã làm đề này — để trang chi tiết mở được kết quả lần trước trước khi làm lại.
        $payload['attempt'] = $this->tests->attemptFor($request->user(), $detail);

        return response()->json($payload);
    }
}
