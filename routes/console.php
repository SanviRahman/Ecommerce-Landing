<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('courier:sync-steadfast-statuses --limit=100')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('courier:sync-pathao-statuses --limit=20 --delay=1000')
    ->everyFiveMinutes()
    ->withoutOverlapping();
