<?php

use App\Services\SettingService;

if (! function_exists('setting')) {
    /**
     * Đọc một cấu hình hệ thống (đã cast) qua SettingService (có cache).
     * Ví dụ: setting('exam.leave_limit', 3).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingService::class)->get($key, $default);
    }
}
