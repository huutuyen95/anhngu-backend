<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Services\StudentNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentNotificationController extends Controller
{
    public function __construct(private readonly StudentNotificationService $service) {}

    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $filter = $request->validated()['filter'] ?? 'all';
        $page = $this->service->list($request->user(), $filter);

        return ApiResponse::paginated(NotificationResource::collection($page->items()), $page);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->service->unreadCount($request->user())]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        abort_unless($this->service->markRead($request->user(), $id), 404, 'Không tìm thấy thông báo.');

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        return response()->json(['updated' => $this->service->markAllRead($request->user())]);
    }
}
