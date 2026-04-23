<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sprint C.11 — prevent double-booking of a tire storage slot.
 *
 * A partial unique index ensures that within a single tenant, only ONE row
 * with the same storage_location can have status='stored'. Tires in any other
 * state (ready_pickup, delivered, maintenance, disposed) don't count, so a
 * slot can legitimately be re-used after a customer picks up their old set.
 *
 * WHY a partial index (not a table-wide unique):
 *   A table-wide unique would prevent historical records from keeping their
 *   storage_location after pickup, which we want to preserve for the audit trail.
 *
 * SQLITE COMPATIBILITY:
 *   SQLite supports partial indexes via CREATE UNIQUE INDEX ... WHERE clauses,
 *   so this migration works in both prod and test environments.
 *
 * DATA AUDIT BEFORE LOCK:
 *   Before adding the index we check for existing duplicates. If any exist,
 *   the migration aborts with an error listing them — the operator must
 *   resolve duplicates manually before running the migration again.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pre-flight: find any existing duplicates that would violate the constraint.
        $duplicates = DB::select("
            SELECT tenant_id, storage_location, COUNT(*) as n
            FROM tires
            WHERE status = 'stored'
              AND storage_location IS NOT NULL
              AND storage_location != ''
            GROUP BY tenant_id, storage_location
            HAVING COUNT(*) > 1
        ");

        if (! empty($duplicates)) {
            $msg = "Cannot add unique constraint on tire storage_location: duplicates exist.\n";
            foreach ($duplicates as $dup) {
                $msg .= sprintf(
                    "  tenant_id=%s location=%s count=%d\n",
                    $dup->tenant_id,
                    $dup->storage_location,
                    $dup->n
                );
            }
            $msg .= 'Resolve duplicates manually before running this migration.';
            throw new \RuntimeException($msg);
        }

        // Partial unique index — constraint only applies to active storage.
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS tires_tenant_location_stored_unique
            ON tires (tenant_id, storage_location)
            WHERE status = 'stored'
              AND storage_location IS NOT NULL
              AND storage_location != ''
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tires_tenant_location_stored_unique');
    }
};
