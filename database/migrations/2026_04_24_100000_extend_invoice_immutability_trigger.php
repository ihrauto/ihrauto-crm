<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extend the PostgreSQL invoice-immutability trigger to cover the full set
 * of fields the Invoice model already considers immutable.
 *
 * WHY:
 *   The original trigger (2026_04_10_100000) locked:
 *     invoice_number, subtotal, tax_total, total, issue_date
 *
 *   The Invoice model's IMMUTABLE_FIELDS constant goes further:
 *     + customer_id, vehicle_id, discount_total
 *
 *   This leaves a gap where a raw SQL UPDATE or a bulk
 *   `Invoice::where(...)->update([...])` bypass (both skip the Eloquent
 *   `updating` hook) can silently rewrite the discount or reassign the
 *   invoice to a different customer or vehicle on an already-issued
 *   invoice — breaking audit integrity.
 *
 *   This migration re-defines the trigger function to also check those
 *   three columns. `CREATE OR REPLACE FUNCTION` leaves the trigger
 *   binding intact; no trigger recreation is required.
 *
 * SQLite compatibility: Postgres-only, model-level protection still covers
 * SQLite test runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_issued_invoice_modification()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Unissued (draft) invoices are freely editable.
                IF OLD.locked_at IS NULL THEN
                    RETURN NEW;
                END IF;

                -- Voiding an issued invoice is always allowed.
                IF NEW.status = 'void' AND OLD.status != 'void' THEN
                    RETURN NEW;
                END IF;

                -- Locked identity + financial fields cannot change once issued.
                IF OLD.invoice_number  IS DISTINCT FROM NEW.invoice_number
                    OR OLD.subtotal       IS DISTINCT FROM NEW.subtotal
                    OR OLD.tax_total      IS DISTINCT FROM NEW.tax_total
                    OR OLD.discount_total IS DISTINCT FROM NEW.discount_total
                    OR OLD.total          IS DISTINCT FROM NEW.total
                    OR OLD.issue_date     IS DISTINCT FROM NEW.issue_date
                    OR OLD.customer_id    IS DISTINCT FROM NEW.customer_id
                    OR OLD.vehicle_id     IS DISTINCT FROM NEW.vehicle_id
                THEN
                    RAISE EXCEPTION 'Cannot modify financial or identity fields on issued invoice %', OLD.id
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Restore the prior (narrower) definition from 2026_04_10_100000.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_issued_invoice_modification()
            RETURNS TRIGGER AS $$
            BEGIN
                IF OLD.locked_at IS NULL THEN
                    RETURN NEW;
                END IF;

                IF NEW.status = 'void' AND OLD.status != 'void' THEN
                    RETURN NEW;
                END IF;

                IF OLD.invoice_number IS DISTINCT FROM NEW.invoice_number
                    OR OLD.subtotal     IS DISTINCT FROM NEW.subtotal
                    OR OLD.tax_total    IS DISTINCT FROM NEW.tax_total
                    OR OLD.total        IS DISTINCT FROM NEW.total
                    OR OLD.issue_date   IS DISTINCT FROM NEW.issue_date
                THEN
                    RAISE EXCEPTION 'Cannot modify financial fields on issued invoice %', OLD.id
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }
};
