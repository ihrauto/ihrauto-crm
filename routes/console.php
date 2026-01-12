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
