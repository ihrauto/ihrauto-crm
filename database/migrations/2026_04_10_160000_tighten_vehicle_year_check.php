<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sprint D.1 — harmonize vehicle year validation with application rules.
 *
 * The original CHECK constraint allowed `EXTRACT(YEAR FROM NOW()) + 2`, but the
 * form validator only allowed `current_year + 1`. When a user somehow submitted
 * `current_year + 2` (e.g. via the API or a direct model save), it slipped past
 * the form but hit the DB — a confusing error path.
 *
 * This migration tightens the DB check to match the app rule: current year + 1.
 *
 * The check is a non-breaking tightening: any row that was valid under the old
 * rule (year <= now+1) is still valid under the new rule. Rows inserted with
 * year = now+2 (if any) would become invalid — we check and warn before
 * applying.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Pre-flight: any rows that would fail the new constraint?
        $violators = DB::select('
            SELECT id, year FROM vehicles
            WHERE year > EXTRACT(YEAR FROM NOW()) + 1
        ');

        if (! empty($violators)) {
            $details = collect($violators)
                ->map(fn ($row) => "id={$row->id} year={$row->year}")
                ->join(', ');

            throw new \RuntimeException(
                "Cannot tighten vehicle year constraint: {$details}. "
                .'Correct these rows first, then re-run the migration.'
            );
        }

        // Drop the old constraint if it exists and re-create with tighter bound.
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS chk_vehicle_year');
        DB::statement(
            'ALTER TABLE vehicles ADD CONSTRAINT chk_vehicle_year '
            .'CHECK (year >= 1900 AND year <= EXTRACT(YEAR FROM NOW()) + 1)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Restore the looser version so a rollback doesn't require editing data.
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS chk_vehicle_year');
        DB::statement(
            'ALTER TABLE vehicles ADD CONSTRAINT chk_vehicle_year '
            .'CHECK (year >= 1900 AND year <= EXTRACT(YEAR FROM NOW()) + 2)'
        );
    }
};
