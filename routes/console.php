<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the appointment completion command to run every 5 minutes
Schedule::command('appointments:complete-expired')->everyFiveMinutes();

// Send appointment reminders every day at 9:00 AM
Schedule::command('appointments:send-reminders')->dailyAt('09:00');

// Cleanup old notifications every week on Sunday at midnight
Schedule::command('notifications:cleanup --days=90')->weeklyOn(0, '00:00');

// Process queued jobs (if using database queue driver)
Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();
