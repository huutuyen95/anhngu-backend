<?php

use App\Http\Middleware\EnsureNotMaintenance;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTokenScope;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'token' => EnsureTokenScope::class,
            'maintenance' => EnsureNotMaintenance::class,
            'superadmin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // App API-only: mọi lỗi ở /api/* trả JSON (kể cả khi request Accept: text/html,
        // vd mở link export trên trình duyệt) — tránh redirect tới route "login" không tồn tại
        // gây RouteNotFoundException; thay vào đó trả 401/403 JSON đúng nghĩa.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );

        // Chưa đăng nhập ở /api/* → 401 JSON, KHÔNG redirect tới route "login" (không tồn tại).
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 401);
            }
        });
    })->create();
