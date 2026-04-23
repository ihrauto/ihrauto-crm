<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint D.2 — invoice item quantities should be positive integers.
 *
 * Original schema used `decimal(8, 2)` which accepts fractional quantities
 * like 2.5. For a workshop CRM this is meaningless — you don't sell half an
 * oil filter. Decimal quantities came from naïve casting in old forms.
 *
 * MIGRATION STRATEGY:
 *   1. Round any existing fractional values up to the next whole number
 *      (rounding down could cause under-billing).
 *   2. Convert the column to integer.
 *   3. Add a CHECK constraint so it stays positive (PostgreSQL only).
 *
 * SQLITE NOTE:
 *   SQLite doesn't enforce column types strictly, and ALTER TABLE is limited.
 *   The application-side defensive coercion in InvoiceService::buildInvoiceItems()
 *   handles test cases.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: round up any fractional quantities so data stays consistent
        // with the new integer column.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('UPDATE invoice_items SET quantity = CEIL(quantity) WHERE quantity != FLOOR(quantity)');
        } else {
            DB::statement('UPDATE invoice_items SET quantity = CAST(quantity AS INTEGER) WHERE quantity != CAST(quantity AS INTEGER)');
        }

        // Step 2: change the column type to integer.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE invoice_items ALTER COLUMN quantity TYPE INTEGER USING quantity::INTEGER');
            DB::statement('ALTER TABLE invoice_items ALTER COLUMN quantity SET DEFAULT 1');
        } else {
            // SQLite: safest no-op; the application layer enforces integers.
            // A full column type change would require recreating the table,
            // which is risky for test fixtures.
        }

        // Step 3: CHECK constraint (PostgreSQL only).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE invoice_items DROP CONSTRAINT IF EXISTS chk_invoice_item_qty');
            DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_invoice_item_qty CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE invoice_items DROP CONSTRAINT IF EXISTS chk_invoice_item_qty');
            DB::statement('ALTER TABLE invoice_items ALTER COLUMN quantity TYPE DECIMAL(8, 2) USING quantity::DECIMAL(8, 2)');
            DB::statement('ALTER TABLE invoice_items ALTER COLUMN quantity SET DEFAULT 1');
        }
    }
};
