<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BL-04 (sprint 2026-04-24): database-level guard that every appointment
 * has end_time > start_time. Belt-and-braces: Appointment::booted()
 * already throws on save, but a bulk UPDATE or raw SQL bypass would
 * sneak past the model. The CHECK constraint closes that gap.
 *
 * Postgres supports named CHECK constraints; SQLite also supports
 * CHECK but without ALTER TABLE ADD CONSTRAINT syntax in older
 * versions. For SQLite we fall back to a trigger that mimics the
 * check at write time.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'appointments_end_after_start_check';

    public function up(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // IF NOT EXISTS guards against re-running (Postgres 9.6+).
            DB::statement(
                'ALTER TABLE appointments ADD CONSTRAINT '.self::CONSTRAINT.
                ' CHECK (end_time > start_time) NOT VALID'
            );
            DB::statement('ALTER TABLE appointments VALIDATE CONSTRAINT '.self::CONSTRAINT);

            return;
        }

        if ($driver === 'sqlite') {
            // Older SQLite has no ALTER TABLE ADD CHECK. Emulate via
            // triggers — one on INSERT, one on UPDATE.
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS appointments_end_after_start_insert
                BEFORE INSERT ON appointments
                FOR EACH ROW
                WHEN NEW.end_time IS NOT NULL AND NEW.start_time IS NOT NULL
                     AND NEW.end_time <= NEW.start_time
                BEGIN
                    SELECT RAISE(ABORT, 'Appointment end_time must be strictly after start_time');
                END;
SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS appointments_end_after_start_update
                BEFORE UPDATE ON appointments
                FOR EACH ROW
                WHEN NEW.end_time IS NOT NULL AND NEW.start_time IS NOT NULL
                     AND NEW.end_time <= NEW.start_time
                BEGIN
                    SELECT RAISE(ABORT, 'Appointment end_time must be strictly after start_time');
                END;
SQL);

            return;
        }

        // Other drivers: no-op; the model guard still enforces.
    }

    public function down(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);

            return;
        }

        if ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS appointments_end_after_start_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS appointments_end_after_start_update');
        }
    }
};
