<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// App API-only, không có trang đăng nhập web. Route "login" tồn tại chỉ để guard auth
// redirect tới đây (thay vì ném RouteNotFoundException) và trả 401 JSON đúng nghĩa —
// vd khi mở link export/tải file trên trình duyệt mà chưa có token.
Route::get('/login', fn () => response()->json(['message' => 'Chưa đăng nhập.'], 401))
    ->name('login');
