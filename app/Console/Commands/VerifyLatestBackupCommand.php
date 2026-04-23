<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Bug review OPS-08.
 *
 * Backups upload to S3 automatically via Spatie's backup:run. But a
 * silently-broken backup (rotated S3 credentials, zero-byte uploads,
 * corrupted tarball) goes unnoticed until restore day. This command
 * probes the LATEST backup file and verifies:
 *
 *   1. It exists on the configured backup disk.
 *   2. It's larger than a minimum size (0 bytes = failed dump that
 *      completed "successfully" from the scheduler's perspective).
 *   3. The file has a newer-than-24h modified timestamp (catches the
 *      case where the nightly backup just stopped running, so the
 *      latest file is actually days old).
 *
 * Run daily via the scheduler. Sends failure to the standard app
 * logger (-> Sentry) so ops knows to investigate BEFORE the disaster.
 *
 * NOT a full restore-from-backup test — that needs a separate DB. This
 * is the cheap first-line health check.
 */
class VerifyLatestBackupCommand extends Command
{
    protected $signature = 'backup:verify
        {--min-size-bytes=1024 : Minimum acceptable file size (default: 1 KiB)}
        {--max-age-hours=26 : Maximum age of the latest backup (default: 26h — 24h nightly + 2h slack)}';

    protected $description = 'Sanity-check the latest backup exists, is reasonably large, and is recent enough (OPS-08).';

    public function handle(): int
    {
        $disk = (string) config('backup.backup.destination.disks')[0] ?? 'backups';
        $minSize = (int) $this->option('min-size-bytes');
        $maxAgeHours = (int) $this->option('max-age-hours');

        try {
            $storage = Storage::disk($disk);
        } catch (\Throwable $e) {
            return $this->reportFailure("Could not open backup disk '{$disk}': {$e->getMessage()}");
        }

        $files = $storage->allFiles();
        if (empty($files)) {
            return $this->reportFailure("Backup disk '{$disk}' is EMPTY — no backups found. Either the backup job never ran or uploads are failing.");
        }

        // Find the newest .zip (Spatie produces .zip archives by default).
        $latest = null;
        $latestTime = 0;
        foreach ($files as $path) {
            if (! str_ends_with(strtolower($path), '.zip')) {
                continue;
            }
            $mtime = $storage->lastModified($path);
            if ($mtime > $latestTime) {
                $latestTime = $mtime;
                $latest = $path;
            }
        }

        if ($latest === null) {
            return $this->reportFailure("No .zip backup archive found on disk '{$disk}'. Spatie produces .zip files; either uploads are failing or the format was changed.");
        }

        $size = $storage->size($latest);
        if ($size < $minSize) {
            return $this->reportFailure(sprintf(
                'Latest backup %s is %d bytes, below the minimum of %d bytes. Dump likely failed.',
                $latest,
                $size,
                $minSize
            ));
        }

        $ageSeconds = now()->timestamp - $latestTime;
        $ageHours = $ageSeconds / 3600;
        if ($ageHours > $maxAgeHours) {
            return $this->reportFailure(sprintf(
                'Latest backup %s is %.1f hours old, exceeding the %d-hour threshold. The backup job may have stopped running.',
                $latest,
                $ageHours,
                $maxAgeHours
            ));
        }

        $sizeMb = $size / 1024 / 1024;
        $msg = sprintf(
            'Backup verification OK: %s is %.1f MB, %.1f hours old.',
            $latest,
            $sizeMb,
            $ageHours
        );
        $this->info($msg);
        Log::info('backup_verify_ok', [
            'path' => $latest,
            'size_bytes' => $size,
            'age_hours' => round($ageHours, 1),
        ]);

        return self::SUCCESS;
    }

    /**
     * Log + return failure so Sentry picks it up even if the scheduler
     * suppresses stderr.
     */
    private function reportFailure(string $message): int
    {
        $this->error($message);
        Log::error('backup_verify_failed', ['message' => $message]);

        return self::FAILURE;
    }
}
