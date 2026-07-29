<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\JsonResponse;

class ClassroomController extends Controller
{
    /** Danh sách lớp gọn (id, name) cho các select ở khu quản trị. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Classroom::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
