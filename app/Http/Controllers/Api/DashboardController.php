<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(): JsonResponse
    {
        $data = $this->dashboard->data();
        $data['classes'] = ClassroomResource::collection($data['classes']);

        return response()->json($data);
    }
}
