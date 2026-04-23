<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automated Backups
Schedule::command('backup:run --only-db')->daily()->at('02:00');
Schedule::command('backup:clean')->daily()->at('03:00');
Schedule::command('backup:monitor')->daily()->at('03:30');

// B-14: overdue-invoice reminder nudge. Runs once a day at a sensible
// time — debounce lives inside the command so operators don't get spammed.
Schedule::command('invoices:send-overdue-reminders')->dailyAt('08:30');

// B-13: low-stock report — currently logs + flashes to dashboard; when a
// notification channel is wired, this is the call-site to upgrade.
Schedule::command('inventory:low-stock-report')->dailyAt('08:45');
