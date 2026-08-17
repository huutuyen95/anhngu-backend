<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SessionItem\ListSessionItemsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SessionItem;
use App\Services\SessionItemService;
use Illuminate\Http\JsonResponse;

class SessionItemController extends Controller
{
    public function __construct(private readonly SessionItemService $items) {}

    public function index(ListSessionItemsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->items->list((int) $request->validated('session_id'))]);
    }

    public function destroy(SessionItem $sessionItem): JsonResponse
    {
        $this->items->delete($sessionItem);

        return ApiResponse::message('Đã gỡ nội dung khỏi buổi.');
    }
}
