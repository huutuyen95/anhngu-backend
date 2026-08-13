<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chỉ cho tài khoản super admin đi tiếp (Cài đặt hệ thống). Admin thường vẫn bị chặn.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Chỉ super admin mới vào được Cài đặt hệ thống.');
        }

        return $next($request);
    }
}
