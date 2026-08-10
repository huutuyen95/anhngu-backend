<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bảo đảm token đang dùng có ít nhất một trong các phạm vi (ability) yêu cầu.
 *
 * Chỉ áp dụng khi request được xác thực bằng một Sanctum PersonalAccessToken thật.
 * Với xác thực không qua token thật (vd `actingAs` trong test, hay TransientToken),
 * middleware bỏ qua — việc phân quyền theo role vẫn do middleware `role` đảm nhiệm.
 *
 * Nhờ vậy token học sinh (chỉ có ability 'student') KHÔNG thể gọi endpoint khu giáo viên
 * (yêu cầu 'teacher'), tách bạch hai loại token teacher / student.
 */
class EnsureTokenScope
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $token = $user->currentAccessToken();

        // Chỉ ép phạm vi với token thật (bearer). Không có → để middleware role xử lý.
        if ($token instanceof PersonalAccessToken) {
            foreach ($scopes as $scope) {
                if ($token->can($scope)) {
                    return $next($request);
                }
            }

            abort(403, 'Token không có phạm vi phù hợp cho khu vực này.');
        }

        return $next($request);
    }
}
