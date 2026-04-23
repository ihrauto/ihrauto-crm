<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add database-level CHECK constraints for status/type columns.
     * These enforce valid values at the database level as a second defense layer.
     * SQLite doesn't support ALTER TABLE ADD CONSTRAINT, so skip on SQLite.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Work order status
        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT chk_wo_status CHECK (status IN ('created','pending','scheduled','in_progress','waiting_parts','completed','invoiced','cancelled'))");

        // Checkin status
        DB::statement("ALTER TABLE checkins ADD CONSTRAINT chk_checkin_status CHECK (status IN ('pending','in_progress','completed','cancelled'))");

        // Invoice status
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT chk_invoice_status CHECK (status IN ('draft','issued','partial','paid','void'))");

        // Payment method
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_method CHECK (method IN ('cash','card','bank_transfer','other'))");

        // Tire status
        DB::statement("ALTER TABLE tires ADD CONSTRAINT chk_tire_status CHECK (status IN ('stored','ready_pickup','maintenance','delivered','disposed'))");

        // Appointment status
        DB::statement("ALTER TABLE appointments ADD CONSTRAINT chk_appointment_status CHECK (status IN ('scheduled','confirmed','completed','failed','cancelled','no_show'))");

        // Vehicle year range
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT chk_vehicle_year CHECK (year >= 1900 AND year <= EXTRACT(YEAR FROM NOW()) + 2)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE work_orders DROP CONSTRAINT IF EXISTS chk_wo_status');
        DB::statement('ALTER TABLE checkins DROP CONSTRAINT IF EXISTS chk_checkin_status');
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS chk_invoice_status');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS chk_payment_method');
        DB::statement('ALTER TABLE tires DROP CONSTRAINT IF EXISTS chk_tire_status');
        DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS chk_appointment_status');
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS chk_vehicle_year');
    }
};
