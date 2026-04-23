<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scalability BL-4: every Schedule::command() must include `onOneServer()`
// so that when we scale to N app containers, the scheduler only fires the
// command on one of them. Without the guard, each container runs its own
// copy — backup runs twice, emails send twice, sequence locks fight.
//
// `onOneServer()` uses the cache driver (Redis in prod) as the mutual-
// exclusion lock. See https://laravel.com/docs/scheduling#running-tasks-on-one-server

// Automated Backups.
Schedule::command('backup:run --only-db')->daily()->at('02:00')->onOneServer();
Schedule::command('backup:clean')->daily()->at('03:00')->onOneServer();
Schedule::command('backup:monitor')->daily()->at('03:30')->onOneServer();

// Bug review OPS-08: independent health check on the latest backup so a
// silent upload failure (rotated S3 creds, zero-byte dumps, stopped
// scheduler) surfaces in Sentry within 26 hours instead of on restore day.
// Runs after the nightly backup + clean so it probes the freshly-uploaded
// file.
Schedule::command('backup:verify')->dailyAt('04:15')->onOneServer();

// B-14: overdue-invoice reminder nudge. Runs once a day at a sensible
// time — debounce lives inside the command so operators don't get spammed.
Schedule::command('invoices:send-overdue-reminders')->dailyAt('08:30')->onOneServer();

// Auto-issue stale drafts where tenants have opted in via
// settings.auto_issue_drafts_after_days. Runs once per day just before
// the overdue reminder so a freshly-auto-issued invoice is already in
// the right state when the reminder job fires on a subsequent day.
Schedule::command('invoices:auto-issue-stale')->dailyAt('08:15')->onOneServer();

// B-13: low-stock report — logs + optionally emails admins per tenant
// setting. `onOneServer` ensures we don't spam 2× at 200 tenants.
Schedule::command('inventory:low-stock-report')->dailyAt('08:45')->onOneServer();

// Scalability C-3: audit log archival — moves rows older than the
// hot-window cutoff into the archive table so the hot audit_logs stays
// fast. Runs Sunday night when traffic is lowest.
Schedule::command('audit-logs:archive')
    ->weeklyOn(0, '04:00') // Sunday 04:00 UTC
    ->onOneServer();
