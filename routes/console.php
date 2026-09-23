<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('automation:run', function () {
    $result = app(\App\Http\Controllers\Api\AutomationController::class)->run();
    $this->info($result->getData(true)['message'] ?? 'Automation selesai.');
})->purpose('Create approval and AP overdue reminders');

Schedule::command('automation:run')->hourly();
Schedule::command('app:process-approval-escalations --days=3')
    ->dailyAt('08:00')
    ->withoutOverlapping();
