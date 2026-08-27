<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('live-view:cleanup')->everyFiveMinutes();
Schedule::command('inbox:process-reopens')->everyMinute();
Schedule::command('leads:process-reopens')->everyFiveMinutes();
Schedule::command('leads:process-scheduled-statuses')->everyFiveMinutes();
Schedule::command('leads:process-follow-up-days')->dailyAt('06:00');
Schedule::command('inbox:process-scheduled-replies')->everyMinute();
Schedule::command('inbox:sync-mail')
    ->everyMinute()
    ->withoutOverlapping(10);
Schedule::command('facebook:sync-messages')
    ->everyMinute()
    ->withoutOverlapping(10);
Schedule::command('sms:sync-messages')
    ->everyMinute()
    ->withoutOverlapping(10);
