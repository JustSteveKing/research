<?php

use App\Console\Commands\WatchThreadsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly rather than daily: the desktop app is not always running, so a
// once-a-day slot would be missed as often as it was hit. Each thread's own
// cadence decides whether a sweep actually queues anything.
Schedule::command(WatchThreadsCommand::class)->hourly()->withoutOverlapping();
