<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

$timezone = config('app.timezone') ?: 'Asia/Dhaka';

// DB-driven automatic processes (anetbd-style)
Schedule::command('cpagol:run-automatic-processes')
    ->everyMinute()
    ->withoutOverlapping()
    ->timezone($timezone);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();
