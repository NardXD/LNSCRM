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
Schedule::command('leads:process-scheduled-emails')->everyFiveMinutes();
Schedule::command('leads:process-follow-up-days')->dailyAt('06:00');
Schedule::command('inbox:process-scheduled-replies')->everyMinute();

Schedule::command('inbox:sync-mail')
    ->everyMinute()
    ->withoutOverlapping(10);
// Full walk (every well-known folder: Inbox/Drafts/Sent/Trash/Spam, not just the
// Inbox+Sent probe above) so Drafts/Trash/Spam and older history actually get
// synced in the background instead of only on a manual "Sync" click. Cheap once
// each folder's one-time backfill finishes — see OutlookMailService::syncInbox().
Schedule::command('inbox:sync-mail --full')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20);
Schedule::command('facebook:sync-messages')
    ->everyMinute()
    ->withoutOverlapping(10);
Schedule::command('sms:sync-messages')
    ->everyMinute()
    ->withoutOverlapping(10);
Schedule::command('inbox:tag-to-leads Inquiry --shared-inbox=Talk2Us')
    ->everyMinute()
    ->withoutOverlapping(10);
