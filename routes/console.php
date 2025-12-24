<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Daily Reports Scheduler
// Runs every day at 20:00 (8 PM) Sarajevo time
Schedule::command('reports:send-daily')
    ->dailyAt('20:00')
    ->timezone('Europe/Sarajevo')
    ->withoutOverlapping()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('Daily reports sent successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('Daily reports failed to send');
    });
