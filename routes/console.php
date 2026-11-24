<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('appointments:send-reminders')
    ->dailyAt('18:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->evenInMaintenanceMode()
    ->appendOutputTo(storage_path('logs/reminders.log'))
    ->onFailure(function () {
        Log::critical('CRITICAL: Scheduled command [appointments:send-reminders] failed!');
    });


Schedule::command('patients:lift-bans')
    ->dailyAt('00:05')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/lift-bans.log'))
    ->onFailure(function () {
        Log::critical('CRITICAL: Scheduled command [patients:lift-bans] failed!');
    });

Schedule::command('appointments:mark-no-show')
    ->everyFifteenMinutes()
    ->timezone('Asia/Damascus')
    ->withoutOverlapping(15)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/no-show.log'))
    ->onFailure(function () {
        Log::critical('CRITICAL: Scheduled command [appointments:mark-no-show] failed!');
    });


Schedule::command('reports:monthly')
    ->monthlyOn(1, '02:00')
    ->timezone('Asia/Damascus')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/monthly-reports.log'))
    ->onFailure(function () {
        Log::critical('CRITICAL: Scheduled command [reports:monthly] failed!');
    });