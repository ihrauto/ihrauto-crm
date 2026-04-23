<?php

namespace App\Listeners;

use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

/**
 * D-07: backup failure → Sentry.
 *
 * Spatie sends a mail on failure (configured in config/backup.php), but
 * email alone is too easy to miss. This listener calls `report()` so
 * every failure also lands in Sentry with tenant/operational context.
 *
 * Attaches to three events — whichever fires, the same report path runs.
 */
class ReportBackupFailure
{
    public function handleBackupFailure(BackupHasFailed $event): void
    {
        report(new \RuntimeException(
            'Backup failed: '.$event->exception->getMessage(),
            previous: $event->exception,
        ));
    }

    public function handleCleanupFailure(CleanupHasFailed $event): void
    {
        report(new \RuntimeException(
            'Backup cleanup failed: '.$event->exception->getMessage(),
            previous: $event->exception,
        ));
    }

    public function handleUnhealthyBackup(UnhealthyBackupWasFound $event): void
    {
        report(new \RuntimeException(
            'Unhealthy backup detected: '.$event->backupDestinationStatus->getHealthChecksMessage(),
        ));
    }

    public function subscribe($events): array
    {
        return [
            BackupHasFailed::class => 'handleBackupFailure',
            CleanupHasFailed::class => 'handleCleanupFailure',
            UnhealthyBackupWasFound::class => 'handleUnhealthyBackup',
        ];
    }
}
