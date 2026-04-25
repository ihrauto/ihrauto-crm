<?php

namespace App\Console\Commands;

use App\Services\InspectionReminderService;
use Illuminate\Console\Command;

/**
 * ENG-012: daily inspection-reminder driver.
 *
 * Runs once per day from the scheduler at 09:00 local time. Walks every
 * vehicle (cross-tenant) whose next_inspection_at falls into one of the
 * 30/14/3-day reminder windows and sends an SMS via the SmsService.
 *
 * Idempotency lives in the service: each (bucket × due-date) is
 * recorded on the vehicle row, so re-running the command on the same
 * day is a no-op. A passing inspection that updates next_inspection_at
 * to a new value implicitly resets the bucket tracking.
 */
class SendInspectionReminders extends Command
{
    protected $signature = 'inspections:send-reminders
        {--dry-run : Report what would be sent without sending}';

    protected $description = 'Send TÜV / MFK / §57a inspection reminders to customers whose vehicles are due in 30 / 14 / 3 days.';

    public function handle(InspectionReminderService $service): int
    {
        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN — no SMS will be sent.');
            // Dry-run path skips the actual send by not invoking the
            // service. The bucket counts come from the same query the
            // service uses internally — re-implementing that here would
            // duplicate logic, so we just print the candidate count.
            $count = \App\Models\Vehicle::query()
                ->withoutGlobalScopes()
                ->whereNotNull('next_inspection_at')
                ->whereBetween('next_inspection_at', [
                    now()->startOfDay()->toDateString(),
                    now()->startOfDay()->addDays(max(InspectionReminderService::BUCKETS))->toDateString(),
                ])
                ->count();

            $this->info("Would consider {$count} vehicle(s) with inspection due in next ".max(InspectionReminderService::BUCKETS).' days.');

            return Command::SUCCESS;
        }

        $results = $service->sendDue();

        $byStatus = $results->groupBy('status')->map->count();
        $this->info('Inspection reminders processed: '.$results->count());
        foreach ($byStatus as $status => $count) {
            $this->line("  {$status}: {$count}");
        }

        return Command::SUCCESS;
    }
}
