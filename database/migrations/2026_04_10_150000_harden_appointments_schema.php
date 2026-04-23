<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint C.1 + C.3 — Harden the appointments table.
 *
 * What this migration does:
 *   1. Add a proper FOREIGN KEY constraint on appointments.tenant_id → tenants.id
 *      with cascadeOnDelete so orphaned appointments cannot exist after a tenant
 *      is permanently deleted.
 *   2. Add a composite index [tenant_id, start_time] to accelerate the calendar
 *      range queries (which filter by tenant and time range).
 *
 * Why not already done:
 *   The original create migration used bare `unsignedBigInteger('tenant_id')`
 *   with only a single-column index. That works for the global TenantScope but
 *   the database had no structural guarantee that appointments belonged to a
 *   real tenant, and calendar queries with time filters couldn't use the index
 *   efficiently.
 *
 * Safety:
 *   - Uses IF NOT EXISTS guards (via hasIndex) to be re-runnable.
 *   - FK only added if missing — we check foreign key constraints on PostgreSQL.
 *   - Sets any orphaned tenant_id values to NULL before adding FK (defense).
 *   - SQLite-aware: skips FK addition (SQLite ALTER TABLE doesn't support adding
 *     foreign keys to existing tables); tests still get the index.
 */
return new class extends Migration
{
    public function up(): void
    {
        // C.3 — Composite index (tenant_id, start_time)
        if (! $this->hasIndex('appointments', 'appointments_tenant_id_start_time_index')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->index(['tenant_id', 'start_time']);
            });
        }

        // C.1 — FK on tenant_id (skipped on SQLite because ALTER TABLE can't add FKs there)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // First, null out any orphaned tenant_id rows so the FK add won't fail.
        DB::statement('
            UPDATE appointments
            SET tenant_id = NULL
            WHERE tenant_id IS NOT NULL
              AND tenant_id NOT IN (SELECT id FROM tenants)
        ');

        // Check if FK already exists (idempotency).
        $fkExists = collect(DB::select("
            SELECT conname FROM pg_constraint
            WHERE conrelid = 'appointments'::regclass AND contype = 'f'
        "))->pluck('conname')->contains(fn ($name) => str_contains($name, 'tenant_id'));

        if (! $fkExists) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('appointments', function (Blueprint $table) {
                try {
                    $table->dropForeign(['tenant_id']);
                } catch (\Throwable $e) {
                    // FK might not exist; ignore.
                }
            });
        }

        if ($this->hasIndex('appointments', 'appointments_tenant_id_start_time_index')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'start_time']);
            });
        }
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $indexName) {
                return true;
            }
        }

        return false;
    }
};
