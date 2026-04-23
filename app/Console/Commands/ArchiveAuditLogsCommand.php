<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sprint C-3 — move audit_logs rows older than the hot-window cutoff
 * into audit_logs_archive.
 *
 * Why:
 *   - At 200 tenants, audit_logs grows ~100k rows/day = 36M/year. Queries
 *     filtering by tenant_id or model slow linearly with table size.
 *   - Swiss OR Art. 958f requires 10-year retention, so we cannot delete.
 *
 * Strategy:
 *   - Keep the last HOT_RETENTION_DAYS in audit_logs (default: 2 years).
 *   - Copy older rows into audit_logs_archive (same shape + archived_at timestamp).
 *   - Delete the now-archived rows from audit_logs.
 *   - All inside a single DB transaction per batch so a failure can't leave
 *     a row in both tables.
 *
 * Performance:
 *   - Chunked by created_at to avoid huge single transactions.
 *   - Default chunk = 5 000 rows. At 200 tenants that's ~7 min per run
 *     for the first catch-up cycle, then a few seconds per week
 *     afterwards once the backlog is drained.
 */
class ArchiveAuditLogsCommand extends Command
{
    protected $signature = 'audit-logs:archive
        {--days=730 : Keep this many days in the hot table (default: 2 years)}
        {--chunk=5000 : Rows per transaction batch}
        {--dry-run : Report what would move without touching rows}';

    protected $description = 'Move audit_logs rows older than the retention cutoff into audit_logs_archive (Swiss OR 958f compliance).';

    public function handle(): int
    {
        $days = max(30, (int) $this->option('days')); // never archive rows <30d old
        $chunkSize = max(100, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subDays($days);

        $candidateCount = DB::table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($candidateCount === 0) {
            $this->info("No audit_logs rows older than {$days} days. Nothing to do.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s rows older than %s — archiving in chunks of %d%s',
            number_format($candidateCount),
            $cutoff->toDateString(),
            $chunkSize,
            $dryRun ? ' (DRY RUN)' : ''
        ));

        $archived = 0;

        // Loop in chunks, re-querying each time because DELETE removes
        // rows so we can't use a fixed offset.
        while (true) {
            $ids = DB::table('audit_logs')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            if ($dryRun) {
                $this->line('  [dry-run] would archive '.$ids->count().' rows (ids: '.$ids->first().' … '.$ids->last().')');
                // In dry-run we DON'T actually pluck the next chunk — that would loop forever.
                // Break out; the candidate count we already printed is enough info.
                break;
            }

            DB::transaction(function () use ($ids, &$archived) {
                // Insert-select from live to archive in a single SQL
                // statement so we don't round-trip row-by-row.
                DB::statement(
                    'INSERT INTO audit_logs_archive (id, tenant_id, user_id, action, model_type, model_id, changes, ip_address, created_at, updated_at, archived_at)
                     SELECT id, tenant_id, user_id, action, model_type, model_id, changes, ip_address, created_at, updated_at, ? AS archived_at
                     FROM audit_logs WHERE id IN ('.$ids->implode(',').')',
                    [now()]
                );

                $deleted = DB::table('audit_logs')->whereIn('id', $ids)->delete();
                $archived += $deleted;
            });

            $this->line("  archived {$archived} / {$candidateCount}...");
        }

        $this->info("Done — archived {$archived} rows.");

        return self::SUCCESS;
    }
}
