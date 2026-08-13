<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chế độ bảo trì: chặn khu học sinh (trả 503 kèm thông báo), khu giáo viên/admin vẫn vào.
 * Chỉ chặn khi người dùng đang đăng nhập là học sinh — cô giáo không bị ảnh hưởng.
 */
class EnsureNotMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isStudent() && setting('system.maintenance', false)) {
            return response()->json([
                'code' => 'maintenance',
                'message' => setting('system.maintenance_message', 'Hệ thống đang bảo trì, em quay lại sau nhé!'),
            ], 503);
        }

        return $next($request);
    }
}
