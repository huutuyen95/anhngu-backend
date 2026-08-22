<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nhắc nhiệm vụ sắp đến hạn — chạy hằng ngày.
Schedule::command('notifications:deadline-soon')->dailyAt('07:00');
