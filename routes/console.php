<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nhắc nhiệm vụ sắp đến hạn — chạy hằng ngày.
// Giờ gửi nhắc hằng ngày theo cấu hình notify.daily_send_time (mặc định 19:00).
Schedule::command('notifications:deadline-soon')->dailyAt(setting('notify.daily_send_time', '19:00'));
