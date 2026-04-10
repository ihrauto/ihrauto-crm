<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add a PostgreSQL BEFORE UPDATE trigger that enforces invoice immutability
 * at the database level.
 *
 * WHY:
 *   The Invoice model's boot() method already blocks modifications via Eloquent
 *   events, but that protection is bypassed by bulk updates:
 *     - Invoice::where(...)->update(['total' => 999])
 *     - DB::table('invoices')->where(...)->update(...)
 *     - Raw SQL UPDATE
 *   A database trigger catches ALL of these.
 *
 * WHAT IS LOCKED:
 *   Once an invoice has been issued (`locked_at IS NOT NULL`), these columns
 *   cannot be changed unless the new status is 'void':
 *     - invoice_number, subtotal, tax_total, total, issue_date
 *
 * WHAT IS STILL ALLOWED on a locked invoice:
 *   - Transition to status='void' (voiding is intentional)
 *   - Updates to paid_amount (payments are tracked post-issue)
 *   - Updates to voided_at, voided_by, void_reason
 *   - updated_at timestamp
 *
 * SQLite compatibility: this migration is PostgreSQL-only. Tests use SQLite
 * and rely on the model-level protection via boot().
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

                -- Locked financial fields cannot change once issued.
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

            DROP TRIGGER IF EXISTS invoice_immutability_trigger ON invoices;
            CREATE TRIGGER invoice_immutability_trigger
                BEFORE UPDATE ON invoices
                FOR EACH ROW
                EXECUTE FUNCTION prevent_issued_invoice_modification();
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS invoice_immutability_trigger ON invoices;');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_issued_invoice_modification;');
    }
};
